---
kind: external_dependency
name: GitHub Container Registry (GHCR)
slug: ghcr
category: external_dependency
category_hints:
    - vendor_identity
scope:
    - '**'
---

### GitHub Container Registry
- Role: Docker image registry hosting the production image `ghcr.io/dikksap/laravel_rsvhelmet`.
- Package visibility must be set to Public in GitHub Packages settings so the target server can pull without repeated token login.