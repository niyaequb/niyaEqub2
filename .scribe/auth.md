# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_JWT_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

To authenticate requests, pass your JWT token in the <code>Authorization</code> header as <code>Bearer {YOUR_JWT_TOKEN}</code>. You can obtain a token by calling <code>POST /api/auth/login</code> or <code>POST /api/auth/verify-otp</code>.
