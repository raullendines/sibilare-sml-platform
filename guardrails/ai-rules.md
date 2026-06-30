# AI Rules

## Provider Calls

- AI providers must be called from backend or workers only.
- Never expose provider API keys to frontend.
- Every AI call must be traceable.

## Prompt Governance

- Prompts must be versioned.
- Structured outputs must be schema-validated.
- Critical outputs should support human review.

## Usage And Cost

- Record AI usage in `usage_ledger`.
- Track model, tokens, cost and source entity.
- Enforce client limits for chatbot and other AI features.

