"""
G&G Support Portal — AI Agent Backend (FastAPI)
================================================
Architecture (as per AI Agent Concepts reference):
  1. RAG  — Retrieval-Augmented Generation
             Retrieves relevant knowledge base chunks BEFORE calling the LLM,
             so the LLM only answers from your private solutions — no hallucinations.

  2. Semantic Search via Embeddings
             Text is converted to high-dimensional vectors (nomic-embed-text via Ollama).
             Meaning is captured mathematically, so "can't log in" matches "password reset".

  3. Local LLM via Ollama
             All AI runs on-premise — no data leaves the internal network.
             LLM: llama3 (swappable to deepseek-r1, qwen3, mistral, phi4, etc.)
             Embed: nomic-embed-text

  4. Vector Database — ChromaDB
             Stores embeddings persistently on disk.
             Used as the source for KNN retrieval.

  5. LangChain Orchestration
             RecursiveCharacterTextSplitter  → chunks solutions into 500-char overlapping pieces
             OllamaEmbeddings                → wraps Ollama embedding model
             Chroma (LangChain wrapper)      → manages the vector store
             ChatOllama                      → wraps Ollama LLM
             KNN + cosine similarity (sklearn) → exact nearest-neighbour retrieval

  6. FastAPI Microservice
             PHP portal proxies to this on port 8000 (internal only).
             If this service crashes, the main PHP portal stays online.

Run:
    pip install -r requirements.txt
    python main.py
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

# ── LangChain imports ─────────────────────────────────────────────────────────
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_ollama import OllamaEmbeddings, ChatOllama
from langchain_chroma import Chroma
from langchain_core.documents import Document
from langchain_core.messages import HumanMessage, SystemMessage

# ── KNN cosine similarity (sklearn) ──────────────────────────────────────────
from sklearn.neighbors import NearestNeighbors
import numpy as np

import re
import requests
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# ── Config ────────────────────────────────────────────────────────────────────
OLLAMA_BASE      = "http://localhost:11434"
EMBED_MODEL      = "nomic-embed-text"   # ollama pull nomic-embed-text
LLM_MODEL        = "llama3"             # ollama pull llama3  (or deepseek-r1, qwen3, mistral …)
CHROMA_PATH      = "./chroma_db"
COLLECTION_NAME  = "gg_solutions"
TOP_K            = 4                    # how many chunks KNN retrieves
COSINE_THRESHOLD = 0.45                 # max cosine distance — lower = stricter match
                                        # cosine distance = 1 - cosine_similarity
                                        # 0.0 = identical  |  1.0 = completely unrelated

# ── LangChain components ──────────────────────────────────────────────────────
embeddings_model = OllamaEmbeddings(
    model=EMBED_MODEL,
    base_url=OLLAMA_BASE,
)

text_splitter = RecursiveCharacterTextSplitter(
    # RecursiveCharacterTextSplitter tries to split on paragraph breaks (\n\n),
    # then line breaks (\n), then sentences, then words — falling back to characters.
    # This keeps semantically coherent pieces together better than a naive slicer.
    chunk_size=500,
    chunk_overlap=80,
    separators=["\n\n", "\n", ". ", " ", ""],
)

llm = ChatOllama(
    model=LLM_MODEL,
    base_url=OLLAMA_BASE,
    temperature=0,          # deterministic answers — no creative guessing
)

vector_store = Chroma(
    collection_name=COLLECTION_NAME,
    embedding_function=embeddings_model,
    persist_directory=CHROMA_PATH,
)

# ── FastAPI app ───────────────────────────────────────────────────────────────
app = FastAPI(title="G&G AI Agent", version="2.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1"],
    allow_methods=["GET", "POST"],
    allow_headers=["Content-Type"],
)


# ── Pydantic models ───────────────────────────────────────────────────────────
class ChatRequest(BaseModel):
    question: str
    history:  list[dict] = []   # [{"role": "user"|"assistant", "content": "..."}]

class IngestRequest(BaseModel):
    solutions: list[dict]       # [{"id": 1, "question": "...", "answer": "..."}]


# ── KNN retrieval with cosine similarity ──────────────────────────────────────
def knn_retrieve(question: str) -> list[dict]:
    """
    Exact KNN search using sklearn NearestNeighbors with cosine metric.

    Why not just use ChromaDB's built-in HNSW?
    HNSW is *approximate* nearest neighbour — it trades a small amount of
    accuracy for speed at very large scale. For a support portal knowledge base
    (hundreds to low thousands of chunks) exact KNN via sklearn is:
      - More accurate (no approximation error)
      - Fast enough (exact KNN on <10k vectors is near-instant)
      - Transparent — we can inspect every cosine distance directly

    Steps:
      1. Embed the question → query vector
      2. Fetch ALL stored vectors from ChromaDB
      3. Run sklearn KNN (cosine) to find the TOP_K closest chunks
      4. Filter out chunks whose cosine distance > COSINE_THRESHOLD
    """
    # Step 1 — get all stored documents + their embeddings from ChromaDB
    collection = vector_store._collection
    total = collection.count()
    if total == 0:
        return []

    stored = collection.get(include=["documents", "metadatas", "embeddings"])
    all_embeddings = np.array(stored["embeddings"])   # shape: (n_chunks, embed_dim)
    all_documents  = stored["documents"]
    all_metadatas  = stored["metadatas"]

    # Step 2 — embed the question
    q_vec = np.array(embeddings_model.embed_query(question)).reshape(1, -1)

    # Step 3 — exact KNN with cosine distance
    k = min(TOP_K, total)
    knn = NearestNeighbors(n_neighbors=k, metric="cosine", algorithm="brute")
    knn.fit(all_embeddings)
    distances, indices = knn.kneighbors(q_vec)  # distances are cosine distances (1 - similarity)

    # Step 4 — build result list, applying threshold filter
    results = []
    for dist, idx in zip(distances[0], indices[0]):
        cosine_similarity = round(1 - float(dist), 4)   # convert distance → similarity score
        cosine_distance   = round(float(dist), 4)

        logger.info(
            f"Chunk: '{all_metadatas[idx].get('title','')[:60]}' | "
            f"cosine_similarity={cosine_similarity} | cosine_distance={cosine_distance}"
        )

        if cosine_distance < COSINE_THRESHOLD:          # only keep close matches
            results.append({
                "text":               all_documents[idx],
                "title":              all_metadatas[idx].get("title", ""),
                "sol_id":             all_metadatas[idx].get("sol_id", ""),
                "cosine_distance":    cosine_distance,
                "cosine_similarity":  cosine_similarity,
            })

    # Sort by closest first
    results.sort(key=lambda x: x["cosine_distance"])
    return results


# ── RAG prompt builder ────────────────────────────────────────────────────────
def build_rag_prompt(question: str, chunks: list[dict], history: list[dict]) -> list:
    """
    Builds the message list for ChatOllama.
    Injects retrieved knowledge base chunks into the system prompt so the LLM
    answers only from your solutions — this is the 'Augmented Generation' in RAG.
    """
    context_parts = []
    for i, c in enumerate(chunks, 1):
        context_parts.append(
            f"[Solution {i}] {c['title']}\n"
            f"Relevance: {int(c['cosine_similarity'] * 100)}%\n"
            f"{c['text']}"
        )

    context_str = "\n\n".join(context_parts) if context_parts else \
        "No relevant solutions found in the knowledge base."

    system_text = (
        "You are the G&G Support Portal AI assistant.\n"
        "Your ONLY source of information is the KNOWLEDGE BASE provided below.\n\n"
        "STRICT RULES — follow without exception:\n"
        "1. ONLY answer using information explicitly present in the KNOWLEDGE BASE.\n"
        "2. NEVER use your own training knowledge, general IT knowledge, or the internet.\n"
        "3. NEVER guess, infer, or make up an answer.\n"
        "4. If the answer is not in the KNOWLEDGE BASE, respond with exactly:\n"
        "   'I'm sorry, I don't have a solution for that in my knowledge base. "
        "Please raise a support ticket and our team will assist you.'\n"
        "5. Do NOT mention other tools, websites, or external resources.\n"
        "6. Be concise, friendly, and professional.\n\n"
        "--- KNOWLEDGE BASE ---\n"
        f"{context_str}\n"
        "--- END KNOWLEDGE BASE ---\n\n"
        "Remember: if the answer is not explicitly in the KNOWLEDGE BASE, say you don't have it."
    )

    messages = [SystemMessage(content=system_text)]

    # Inject last 3 conversation turns for context continuity
    for turn in history[-6:]:
        if turn["role"] == "user":
            messages.append(HumanMessage(content=turn["content"]))
        else:
            # LangChain AIMessage for assistant turns
            from langchain.schema import AIMessage
            messages.append(AIMessage(content=turn["content"]))

    messages.append(HumanMessage(content=question))
    return messages


# ── Routes ────────────────────────────────────────────────────────────────────

@app.get("/health")
def health():
    """Liveness probe — called by PHP admin health-check."""
    try:
        r = requests.get(f"{OLLAMA_BASE}/api/tags", timeout=5)
        ollama_ok = r.status_code == 200
    except Exception:
        ollama_ok = False

    total_chunks = vector_store._collection.count()

    return {
        "status":            "ok",
        "ollama":            ollama_ok,
        "llm_model":         LLM_MODEL,
        "embed_model":       EMBED_MODEL,
        "retriever":         f"KNN (sklearn, cosine, k={TOP_K}, threshold={COSINE_THRESHOLD})",
        "chunking":          "RecursiveCharacterTextSplitter (size=500, overlap=80)",
        "solutions_indexed": total_chunks,
    }


@app.post("/chat")
def chat(req: ChatRequest):
    """
    Main RAG endpoint — proxied by includes/ai_chat.php.

    Flow:
      question → KNN cosine retrieval → threshold filter → RAG prompt → ChatOllama → answer
    """
    question = req.question.strip()
    if not question:
        raise HTTPException(status_code=400, detail="Question is empty.")

    try:
        # Step 1: Retrieve top-K chunks via exact KNN cosine similarity
        chunks = knn_retrieve(question)

        # Step 2: If nothing close enough in the knowledge base, skip LLM entirely
        if not chunks:
            logger.info(f"No close match found for: '{question}'")
            return {
                "success": True,
                "answer":  (
                    "I'm sorry, I don't have a solution for that in my knowledge base. "
                    "Please raise a support ticket and our team will assist you."
                ),
                "sources": [],
                "retrieval_scores": [],
            }

        # Step 3: Build RAG prompt and call LLM
        messages = build_rag_prompt(question, chunks, req.history)
        response = llm.invoke(messages)
        answer   = response.content

        sources = [{"id": c["sol_id"], "title": c["title"]} for c in chunks]
        retrieval_scores = [
            {
                "title":             c["title"],
                "cosine_similarity": c["cosine_similarity"],
                "cosine_distance":   c["cosine_distance"],
            }
            for c in chunks
        ]

        return {
            "success":          True,
            "answer":           answer,
            "sources":          sources,
            "retrieval_scores": retrieval_scores,   # useful for debugging/tuning threshold
        }

    except requests.exceptions.ConnectionError:
        raise HTTPException(
            status_code=503,
            detail="Ollama is not running. Start it with: ollama serve"
        )
    except Exception as e:
        logger.error(f"Chat error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/ingest")
def ingest(req: IngestRequest):
    """
    Re-index all solutions (called by PHP admin 'Re-index AI' button).
    Full rebuild — clears old vectors and re-chunks + re-embeds everything.

    Flow:
      PHP sends all approved solutions
        → strip HTML (TinyMCE tags)
        → RecursiveCharacterTextSplitter (500 chars, 80 overlap)
        → OllamaEmbeddings (nomic-embed-text)
        → stored in ChromaDB
    """
    if not req.solutions:
        raise HTTPException(status_code=400, detail="No solutions provided.")

    # Full re-index: wipe and rebuild
    global vector_store
    try:
        vector_store._client.delete_collection(COLLECTION_NAME)
        logger.info("Old collection deleted.")
    except Exception:
        pass

    # Reinitialise the LangChain Chroma wrapper after collection deletion
    vector_store = Chroma(
        collection_name=COLLECTION_NAME,
        embedding_function=embeddings_model,
        persist_directory=CHROMA_PATH,
    )

    documents = []

    for sol in req.solutions:
        # Combine question + answer, then strip TinyMCE HTML tags
        raw   = f"{sol.get('question', '')}\n{sol.get('answer', '')}"
        clean = re.sub(r"<[^>]+>", " ", raw)
        clean = re.sub(r"\s+",     " ", clean).strip()
        if not clean:
            continue

        # RecursiveCharacterTextSplitter — standard LangChain chunking
        chunks = text_splitter.split_text(clean)

        for chunk in chunks:
            documents.append(Document(
                page_content=chunk,
                metadata={
                    "sol_id": str(sol["id"]),
                    "title":  sol.get("question", "")[:120],
                }
            ))

    if not documents:
        raise HTTPException(status_code=400, detail="All solutions were empty after cleaning.")

    # Embed + store via LangChain (batches automatically)
    vector_store.add_documents(documents)
    logger.info(f"Ingested {len(documents)} chunks from {len(req.solutions)} solutions.")

    return {
        "success":   True,
        "indexed":   len(documents),
        "solutions": len(req.solutions),
    }


# ── Entry point ───────────────────────────────────────────────────────────────
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=False)