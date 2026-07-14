# LIBEROU TV — Painel Web

Painel PHP para controlar o app Android:

1. **DNS principal de login**
2. **DNS secundários por código** (brinde/revenda — o cliente digita código, não a DNS)
3. **Dispositivos** (MAC / Android ID, TV ou celular, usuário, servidor)
4. **Cards** do dashboard (Live, Filmes, Séries)

## Requisitos

- PHP 8.1+ com extensões `pdo_sqlite`, `json`, `fileinfo`
- HTTPS recomendado no VPS

## Instalação rápida

1. Envie a pasta `liberou-panel` para o servidor (ex: `/var/www/liberou-panel`).
2. Edite `includes/config.php`:
   - `PANEL_PUBLIC_URL` → URL pública real (ex: `https://seudominio.com/liberou-panel`)
   - `API_TOKEN` → o mesmo token no APK (`PanelClient`)
3. Permissões:
   ```bash
   chmod -R 775 data
   chown -R www-data:www-data data
   ```
4. Aponte o vhost/document root para esta pasta (ou subpasta no domínio).
5. Acesse `/login.php`  
   - Usuário: `admin`  
   - Senha: `admin123`  
   **Troque a senha** (por enquanto: recriar hash em `admin_users` no SQLite ou limpar a tabela).

## API usada pelo app

| Endpoint | Método | Função |
|----------|--------|--------|
| `/api/config.php` | GET | DNS + URLs dos cards |
| `/api/heartbeat.php` | POST | Reporta dispositivo |
| `/api/dns_code.php?code=XXX` | GET | Resolve código → DNS secundária |

Header: `X-Api-Token: <API_TOKEN>`

### Exemplo config

```json
{
  "ok": true,
  "login_dns": "http://dns.exemplo.com:8080/",
  "force_dns": true,
  "cards": {
    "live": "https://.../card_live_....png",
    "movies": "https://...",
    "series": "https://..."
  }
}
```

## App Android

O APK chama o painel em background (Splash + Dashboard).  
Configure a URL base no smali `com.liberou.tv.panel.PanelClient` (constante `BASE_URL`) e o `API_TOKEN` igual ao do painel.
