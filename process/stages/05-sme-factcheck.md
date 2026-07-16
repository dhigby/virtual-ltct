# Stage 5 — SME fact-check

**Board status:** `SME Check` · **Who:** SME · **Tool:** human review

The alignment check confirms the content is *structurally* sound; this stage confirms it is
*true*. A subject-matter expert verifies every tool name, version, procedure, and field
detail against reality.

## Entry criteria

- The alignment check ([stage 4](04-alignment.md)) passed.
- Move the board status to `SME Check`.

## Who does this

An SME with real field experience in this course's domain. **The Author may act as SME
only if the Internal Reviewer (stage 6) is a different person** — a course must not be
authored, fact-checked, and reviewed by one person alone. If you don't have the expertise,
route to a colleague SME; the design doc's "SME(s) consulted" field is a starting point.

## How

Read the lessons and quiz against the design doc's **SME knowledge notes** and against your
own knowledge of the domain. Check specifically:

- Tool names, versions, menu paths, and procedures are correct and current.
- Field scenarios are realistic for consultants working with translators and language
  workers in the field.
- No factual claim is wrong, outdated, or oversimplified to the point of being misleading.

Make (or request) corrections directly in the content. When it's accurate, add a comment to
the tracker issue in this format:

```
SME fact-check: pass — <your name>, <YYYY-MM-DD>
Corrections made: <bullet list, or "none">
```

## Exit criteria

- A `SME fact-check: pass` comment is on the tracker issue.
- Any corrections are committed.

## Then

- ✅ Tick **"5. SME fact-check passed"** on the tracker issue.
- Go to [Stage 6 — Internal review](06-internal-review.md).
