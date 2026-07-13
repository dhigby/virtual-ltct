# Markdown Styling Reference

Formatting conventions for lesson files in this repo, and how the markdown maps to
HTML/CSS classes when content is converted for delivery (e.g., upload to Cypher for
Business).

## Lesson file header

Lesson files carry **no YAML frontmatter** (module metadata lives in the module
`README.md`). Every lesson opens:

```markdown
# Lesson Title

**Estimated time:** 45 minutes
```

The `**Estimated time:**` line is required, must be ≤ 90 minutes, and is checked by CI
and the alignment reviewer.

## Heading structure

| Markdown | Use | CSS class (HTML conversion) |
|---|---|---|
| `#` H1 | Lesson title (once per file) | `.module-title` |
| `##` H2 | Major sections (Learning objectives, Connect, Content, Challenge, Change) | `.section-title` |
| `###` H3 | Subsections within major sections | standard H3 with spacing |
| `####` H4 | Sub-subsections (use sparingly) | standard H4 with spacing |

Do not skip heading levels (e.g., H2 → H4).

## Time estimates

The lesson-level `**Estimated time:** X minutes` header is what's required and checked.
Per-phase estimates are optional; when used, put them directly under the H2:

```markdown
## Connect
**Time:** 10 minutes
```

Maps to `.time-estimate` when converted to HTML.

## Emphasis patterns

**Bold (`**text**`)** for labels and key terms:

- `**Key Point:**` — introduces important concepts → `.info-box` or inline emphasis
- `**Example:**` — introduces scenarios
- `**Objective:**` — states activity goals
- `**Instructions:**` — begins procedural steps
- `**Purpose:**` — the lesson's real-world task (preamble)
- `**Watch the video:**` — introduces a linked video

*Italic (`*text*`)* for contextual emphasis within sentences. Use sparingly.

## Callout patterns

**Information callout:**

```markdown
**Key Point:** [Important concept here]
```

**Activity box:**

```markdown
### Activity: [Name]

**Objective:** [Goal]

**Instructions:**
1. Step one
2. Step two
```

Maps to `.activity-box` structure.

**Reflection prompt:**

```markdown
### Reflection Question
[Thoughtful question]
```

Maps to `.reflection-box` structure.

## Lists

Bulleted lists for unordered content; numbered lists for sequential steps.

Key Takeaways (special bulleted list at the end of the Content section):

```markdown
### Key Takeaways

- Major point one
- Major point two
- Major point three
```

## Section separators

If horizontal rules (`---`) are used, place them **only** between the four major phases
(after Connect, after Content, after Challenge). Do not use them within sections.

## Four-phase structure summary

| Phase | Share of a 60-min lesson | Purpose |
|---|---|---|
| Connect | ~10 min | Activate prior knowledge, link to experience |
| Content | ~25–30 min | Core instruction, examples, Key Takeaways |
| Challenge | ~15–20 min | Hands-on practice, guided questions |
| Change | ~5–10 min | Application, next steps, preview |

Scale the phase times proportionally to the lesson's `**Estimated time:**`.

Mentor/assessment guidance does **not** go in the lesson file — it belongs in the
course's `NN-mentor-guide.md` (see `modules/_template/03-mentor-guide.md`).

## HTML conversion notes

When converting markdown to HTML for delivery:

1. Bold labels → `<strong>` tags or special `<div>` blocks
2. Major sections (H2) → `<section>` tags with appropriate classes
3. Lists → styled `<ul>` / `<ol>` with CSS
4. Horizontal rules → styled section dividers

The delivery stylesheet (`course-styles.css`) handles margins, content width,
typography, color coding by content type, and responsive layout.

## Best practices checklist

- [ ] `**Estimated time:** X minutes` right under the H1, ≤ 90 minutes
- [ ] H1 used once (lesson title only)
- [ ] All four phases present as H2s, in order: Connect, Content, Challenge, Change
- [ ] Heading levels not skipped
- [ ] `---` (if used) only between major phases
- [ ] No `[bracketed]` placeholder text left in final output
- [ ] Concrete examples in the Content section, ending with Key Takeaways
- [ ] No "For Mentors" section in the lesson — that content goes in `NN-mentor-guide.md`
