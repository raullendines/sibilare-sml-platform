# AI Architecture

AI is part of the product delivery and must be traceable.

## AI Use Cases

- Post classification.
- Widget insights.
- Pattern detection.
- Chatbot replies.
- Report sections.
- Executive summaries.
- Dashboard personalization assistance.

## Required Tracking

Every AI call should record:

- Prompt template and version.
- Model used.
- Input payload.
- Raw output.
- Final text or structured result.
- Tokens.
- Cost.
- Status.
- Review state when relevant.

## Rules

- Frontend must not call LLM providers directly.
- Prompts must be versioned.
- Classification outputs must be schema-validated.
- Human review must be possible for important classifications and report text.

