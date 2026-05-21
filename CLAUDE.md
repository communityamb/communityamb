# CommunityAmb — Project Instructions

## CTX Pause Rule (Context Window Management)

When context usage reaches **65%** during active work:

1. **Stop active work immediately.** Do not start new file edits or agent launches.
2. **Commit completed work.** Make logical commits for any completed changes in the active worktree(s). Stage specific files, not `git add -A`.
3. **Update `AUDIT-REPORT.md`** with an `## Addendum / Handoff` section containing:
   - Current phase and branch
   - Files changed so far
   - Validation status (what passed, what wasn't checked)
   - Open risks or blockers
   - Exact next step to resume
4. **Update project memory** (`~/.claude/projects/-Users-adammoussa-Documents-repositories-communityamb/memory/project-communityamb.md`) with phase completion status and any new decisions.
5. **Stop with a handoff summary** — phase status table, worktree/branch state, and what the next session should do first.

Do NOT attempt to squeeze in "one more fix" past 65%. The cost of a corrupted context far exceeds the cost of stopping cleanly.

## Audit Execution Context

This project is running a 10-phase audit from `AUDIT-REPORT.md`. Each phase uses a separate git worktree:

```
git worktree add ../communityamb-phase-<N> -b feature/phase-<N>-<short-name>
```

### Phase execution order
1. Phase 1 (security) — first
2. Phases 2 + 3 — parallel after Phase 1
3. Phase 4 — after or alongside Phase 3
4. Phase 5 — after Phases 2-4
5. Phases 6-10 — final polish after 1-5

### Agent usage per phase
Use agents for each phase role:
- `scanner` → affected files / cross-file impact
- `fast_coder` → mechanical fixes
- `reviewer` → regression and quality review
- `cross_reviewer` → security, routes, redirects, build, accessibility, CI/CD
- `researcher` → Statamic/Laravel/Tailwind/security docs
- general purpose → implementation support

Track for each agent: why used, task sent, result, accepted/rejected.

### Worktree management
- Worktrees at `../communityamb-phase-<N>` on branch `feature/phase-<N>-<short-name>`
- All file edits use absolute paths to the worktree directory
- All git commands use `git -C <worktree-path>`
- Old cleanup worktrees exist from prior work — do not delete them

### Phase output format
After each phase, document:
- Phase / Worktree / Branch
- Agents used
- Files changed
- Validation results
- Risks/blockers
- Commits made
- Final status

## Statamic/Antlers Gotchas

- `type: assets` fields cannot store plain URL strings — they attempt asset resolution and return null. Use `type: text` with path strings, or ensure content files use proper asset references.
- `?=` (null coalescing assignment) does NOT work reliably in partials. Use `{{ var ?? 'default' }}` inline.
- Partial parameter named `title` collides with page scope. Use unique names like `input_title`.
- Team member photos use `photo_url` field (not in blueprint) to avoid asset resolution issues.
- Statamic form validation errors use named error bag `form.{handle}`, not the default bag.
- Bard CANNOT render plain markdown content stored in .md files — changing a blueprint field from `type: markdown` to `type: bard` breaks `{{ content }}` rendering (outputs raw markdown text instead of HTML). Keep content fields as `type: markdown` unless content is converted to ProseMirror JSON.
- Tailwind CSS v4 `max-w-*` utilities are generated from `--container-*` theme tokens, NOT `--max-w-*`. Use `--container-<name>` in `@theme` to create `max-w-<name>` utilities.
- Team role select uses display values as both key and label (e.g., `Chief: Chief`) because templates filter with `role:is="Chief"`.

## Phase Validation Checklist (mandatory before merge)

Every phase MUST pass all of these before merging to main. A build success or 200 status is NOT sufficient.

1. **Build check:** `npx vite build` succeeds
2. **Utility class verification:** If new `@theme` tokens were added, grep the built CSS to confirm the expected utility classes exist (e.g., `grep 'max-w-container' public/build/assets/*.css`)
3. **Blueprint field check:** If any blueprint field type was changed, verify `{{ field_name }}` renders the expected HTML on at least one affected page (curl the page and check for raw markdown, empty output, or broken tags)
4. **Visual rendering check:** Start the server (`php artisan serve`), load every modified page in a browser (or curl and inspect the `<main>` HTML), and confirm content renders with proper structure and styling. A 200 response does not mean the page looks correct.
5. **Stache clear:** Run `php artisan statamic:stache:clear && php artisan statamic:stache:warm` after blueprint changes before testing
6. **No stale hot file:** Verify `public/hot` does not exist before testing built assets (delete it if Vite is not running)

## Dev servers
```
composer run dev    # PHP artisan on 8000, Vite on 5173, queue + pail
```
