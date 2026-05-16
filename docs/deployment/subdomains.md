# Subdomain Setup

## Portal Mapping

| Subdomain | Portal | Roles |
|-----------|--------|-------|
| `app.<domain>` or `employee.<domain>` | Employee portal | EMPLOYEE |
| `company.<domain>` | Company portal | COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER |
| `admin.<domain>` | Admin portal | ELYO_ADMIN, ELYO_SUPPORT |
| `partner.<domain>` | Partner portal | PARTNER |

## Architecture

- DNS creates subdomains via A or CNAME records pointing to the same server.
- Nginx maps hostnames via `server_name` directives.
- The same Angular build serves all subdomains.
- Angular uses the hostname only for portal entry/routing convenience.
- The Laravel API remains shared across all portals.
- Backend authorization is mandatory — hostname alone does not grant access.

## Local Development

For local development, no real DNS is required. Options:

1. **Edit `/etc/hosts`** (recommended):
   ```
   127.0.0.1 app.elyo.local
   127.0.0.1 employee.elyo.local
   127.0.0.1 company.elyo.local
   127.0.0.1 admin.elyo.local
   127.0.0.1 partner.elyo.local
   ```

2. **Use `localhost` only**: The Angular app defaults to no specific portal when hostname detection fails. Login redirects based on user roles.

## Example Nginx Config (Production Reference Only)

```nginx
server {
    listen 80;
    server_name app.example.com employee.example.com;
    root /var/www/angular/browser;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
    location /api { proxy_pass http://127.0.0.1:8000; proxy_set_header Host $host; }
}

server {
    listen 80;
    server_name company.example.com;
    root /var/www/angular/browser;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
    location /api { proxy_pass http://127.0.0.1:8000; proxy_set_header Host $host; }
}

server {
    listen 80;
    server_name admin.example.com;
    root /var/www/angular/browser;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
    location /api { proxy_pass http://127.0.0.1:8000; proxy_set_header Host $host; }
}

server {
    listen 80;
    server_name partner.example.com;
    root /var/www/angular/browser;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
    location /api { proxy_pass http://127.0.0.1:8000; proxy_set_header Host $host; }
}
```

> **Note**: Do not modify production Nginx config as part of this task. This is documentation only.
