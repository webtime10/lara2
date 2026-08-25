-- Enable pgvector for embeddings / similarity search
CREATE EXTENSION IF NOT EXISTS vector;

-- Second DB for Laravel RAG (Windows project used rag_core)
SELECT 'CREATE DATABASE rag_core OWNER lara2'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'rag_core')\gexec

\c rag_core
CREATE EXTENSION IF NOT EXISTS vector;
