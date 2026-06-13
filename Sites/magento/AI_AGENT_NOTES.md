# AI Agent Notes

## Magento Frontend UI Changes

This project runs Magento inside Docker. Do not assume edits under `Sites/magento/src` are immediately visible in the browser.

Before declaring a UI change done, always sync and verify from the running container:

```bash
cd Sites/magento
scripts/sync-frontend-ui.sh
```

Why this is required:

- The Docker app uses container files and generated static assets, not every host-side file directly.
- Magento cache and full page cache use Redis, so deleting host `var/cache` is not enough.
- Browser CSS/JS comes from `pub/static` inside the container, with a `static/version...` URL.
- LESS must compile successfully inside Magento; raw CSS functions such as `clamp(...)` may need LESS escaping like `~"clamp(...)"`.

Verification checklist after UI edits:

- Run `scripts/sync-frontend-ui.sh` successfully.
- Confirm the homepage HTML references a new `/static/version.../frontend/Hiddentechies/bizkick/...` asset URL.
- Confirm the served CSS contains the new selector via `curl` or container `grep`.
- Confirm server-rendered template changes appear in the fetched homepage HTML.

Do not patch host `pub/static` as the primary fix. It is generated/ignored and may not be served by the running container.
