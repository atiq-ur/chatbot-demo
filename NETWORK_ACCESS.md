# Nexus AI — Local Network Access

## Domains

| URL | What it is |
|---|---|
| `http://nexusai.test` | Chat frontend |
| `http://nexusai-api.test` | Laravel backend API |

Your machine's LAN IP: **192.168.68.61**

---

## For Colleagues on the Same Wi-Fi/LAN

Add the following two lines to your `/etc/hosts` file (Linux/macOS) or `C:\Windows\System32\drivers\etc\hosts` (Windows):

```
192.168.68.61  nexusai.test
192.168.68.61  nexusai-api.test
```

Then open **http://nexusai.test** in your browser.

### Linux / macOS — edit hosts
```bash
sudo nano /etc/hosts
# paste the two lines above, save with Ctrl+O, exit with Ctrl+X
```

### Windows — edit hosts (run as Administrator)
```
notepad C:\Windows\System32\drivers\etc\hosts
```

---

## For the Host Machine (atiq)

Both domains resolve via Valet's built-in DNS automatically — no hosts changes needed.

---

## Rebuilding the Frontend

After making code changes to the frontend, run from the project root:

```bash
./rebuild.sh
```

This will:
1. Run `npm run build` inside `chat-app/`
2. Restart the `nexusai-frontend` systemd service

---

## Service Management

```bash
# Check status
systemctl --user status nexusai-frontend

# View live logs
journalctl --user -u nexusai-frontend -f

# Stop / start
systemctl --user stop nexusai-frontend
systemctl --user start nexusai-frontend
```
