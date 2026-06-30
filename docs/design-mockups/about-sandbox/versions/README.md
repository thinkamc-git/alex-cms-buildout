# About page — version snapshots

## ⚠ This must be HOSTED, not opened from disk
`file://` breaks the design-system styling (the DS CSS's relative `@import`s and the
iframe-loaded versions don't resolve). **Always serve it over a local web server** and
open the `localhost` URL — this is the standard for every sandbox, so we never have to
push throwaway work to the staging server, and never hand over a file link.

```sh
# from the repo root (php isn't installed; python3 is):
python3 -m http.server 8765 --bind 127.0.0.1
# then open:
#   http://127.0.0.1:8765/docs/design-mockups/about-sandbox/index.html
```

## The switcher
Open the URL above — it's a version switcher with a bar across the top.
Click a version to load it; the bar shows a one-line description of each.
It opens on **v6** (the agreed foundation); **v0** is the current live page (baseline).

| File | What it is |
|------|-----------|
| `v0-original.html` | **The current LIVE about page** (`site/_pages/_bodies/about.html`). Our starting point / reference. |
| `v2-tight-profile.html` | Tightened third-person profile — hero claim + practice + How Alex Works + Hudson's Bay + full **Track Record ledger** + close. (More resume-like.) |
| `v3-miniresume-cases.html` | Scaffold with **expandable "Selected Work" cases** (Situation / What I did / Outcome) + mini-résumé ledger. The "scan, then expand" direction. |
| `v5-calm-context.html` | **Calm context page** — one label-rail grid: Intro · What I do · Credentials (mini-CV → full résumé) · Elsewhere. |
| `v6-original-plus.html` | **Agreed foundation** — the original page, intact, + v2's Track Record mini-résumé (→ full) + a light "Developing the Discipline". |

Not snapshotted (recoverable from the chat if wanted): v1 (long first draft), v4 (transitional thought-leader reframe).

Nothing here is live. To adopt one as the base going forward, we copy the chosen
version into the real page (`site/_pages/_bodies/about.html`).
