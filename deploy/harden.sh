#!/usr/bin/env bash
# One-time server provisioning and hardening (ADR-0019) for Ubuntu 24.04 on the Hostinger KVM.
# Run as root from an unpacked deploy bundle; safe to run again.
#
#   sudo ./deploy/harden.sh <deploy-user> <admin-keys.pub> [ci-deploy-key.pub]
#
# It installs Docker, nginx and certbot, creates the deploy user (SSH keys only, in the docker
# group, allowed to test and reload nginx), turns off root and password logins, opens only
# 22/80/443 in UFW, sets up fail2ban, security-only unattended upgrades, a swap file, Docker log
# rotation, UTC, and the backup, monitor and drill timers. The project lives in /core/mawred
# (MAWRED_ROOT to change it). Keep your current SSH session open until a login as <deploy-user> works.

set -Eeuo pipefail

user="${1:?usage: harden.sh <deploy-user> <admin-keys.pub> [ci-deploy-key.pub]}"
admin_keys="${2:?usage: harden.sh <deploy-user> <admin-keys.pub> [ci-deploy-key.pub]}"
ci_key="${3:-}"
root_dir="${MAWRED_ROOT:-/core/mawred}"
bundle_dir="$(cd "$(dirname "$0")" && pwd)"

log() { printf '\n== %s\n' "$*"; }
die() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

[ "$(id -u)" = 0 ] || die "run as root"
[[ "$user" =~ ^[a-z][a-z0-9_-]{1,30}$ ]] || die "not a valid user name: $user"
grep -qE '^(ssh-ed25519|ssh-rsa|ecdsa-sha2-[a-z0-9-]+|sk-ssh-ed25519@openssh.com) ' "$admin_keys" ||
    die "$admin_keys has no SSH public key; refusing to turn off password logins"
grep -q 'VERSION_ID="24.04"' /etc/os-release || printf 'warning: written for Ubuntu 24.04\n' >&2

log "time zone and base packages"
timedatectl set-timezone UTC
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get upgrade -yq
apt-get install -yq ca-certificates curl gnupg ufw fail2ban unattended-upgrades jq nginx certbot

log "Docker Engine and the Compose plugin (Docker's apt repository)"
if ! command -v docker >/dev/null; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    # shellcheck disable=SC1091 # the host's own os-release
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        >/etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
cat >/etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "5" },
  "live-restore": true
}
JSON
systemctl enable docker >/dev/null
systemctl restart docker

log "deploy user $user"
id -u "$user" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$user"
usermod -aG docker "$user"
home="$(getent passwd "$user" | cut -d: -f6)"
install -d -m 700 -o "$user" -g "$user" "$home/.ssh"
{
    grep -E '^(ssh-|ecdsa-|sk-)' "$admin_keys"
    if [ -n "$ci_key" ]; then
        printf 'restrict,command="%s/deploy/ci-gate.sh" %s\n' "$root_dir" "$(grep -m 1 -E '^(ssh-|ecdsa-)' "$ci_key")"
    fi
} >"$home/.ssh/authorized_keys"
chown "$user:$user" "$home/.ssh/authorized_keys"
chmod 600 "$home/.ssh/authorized_keys"
install -d -m 750 -o "$user" -g "$user" "$root_dir"

log "nginx: running, and reloadable by $user for blue/green switches (D134)"
systemctl enable --now nginx >/dev/null
cat >/etc/sudoers.d/mawred-nginx <<CONF
# deploy/deploy.sh tests and reloads nginx after pointing it at the new app slot.
$user ALL=(root) NOPASSWD: /usr/sbin/nginx -t -q, /usr/bin/systemctl reload nginx
CONF
chmod 440 /etc/sudoers.d/mawred-nginx
visudo -cf /etc/sudoers.d/mawred-nginx >/dev/null

log "SSH: keys only, no root login"
cat >/etc/ssh/sshd_config.d/10-mawred.conf <<CONF
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
LoginGraceTime 30
X11Forwarding no
AllowUsers $user ${MAWRED_EXTRA_SSH_USERS:-}
CONF
sshd -t
systemctl restart ssh

log "firewall: 22, 80, 443 only"
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw limit 22/tcp >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
# Docker publishes ports past UFW: compose.prod.yaml binds every port to 127.0.0.1, keep it so.

log "fail2ban: SSH, plus a longer ban for repeat offenders"
cat >/etc/fail2ban/jail.d/mawred.local <<'CONF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5
backend = systemd

[sshd]
enabled = true

[recidive]
enabled = true
backend = auto
bantime = 1w
findtime = 1d
CONF
systemctl enable fail2ban >/dev/null
systemctl restart fail2ban

log "unattended security upgrades (no automatic reboot: monitor.sh asks for one)"
cat >/etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
CONF
cat >/etc/apt/apt.conf.d/52mawred-unattended-upgrades <<'CONF'
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
CONF

log "swap and kernel settings"
if ! swapon --show | grep -q .; then
    fallocate -l "${SWAP_SIZE:-4G}" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
fi
cat >/etc/sysctl.d/99-mawred.conf <<'CONF'
vm.swappiness = 10
# Redis background saves (AOF rewrite) need overcommit.
vm.overcommit_memory = 1
CONF
sysctl --system >/dev/null

log "journald size limit"
install -d /etc/systemd/journald.conf.d
printf '[Journal]\nSystemMaxUse=1G\n' >/etc/systemd/journald.conf.d/10-mawred.conf
systemctl restart systemd-journald

log "backup, monitor and restore drill timers"
for unit in "$bundle_dir"/systemd/*.service "$bundle_dir"/systemd/*.timer; do
    sed -e "s|@USER@|$user|g" -e "s|@ROOT@|$root_dir|g" "$unit" >"/etc/systemd/system/$(basename "$unit")"
done
systemctl daemon-reload
systemctl enable mawred-backup.timer mawred-monitor.timer mawred-restore-drill.timer >/dev/null
# The timers start once the stack runs (deploy.sh init); until then their scripts stop early.
systemctl start mawred-backup.timer mawred-monitor.timer mawred-restore-drill.timer

log "done"
cat <<NEXT
Next (deploy/runbooks/provision-server.md):
  1. In a NEW terminal, check: ssh $user@<server>   (keep this session open until it works)
  2. As $user: put the bundle and .env.production in $root_dir, docker login ghcr.io
  3. $root_dir/deploy/deploy.sh init <commit-sha>
  4. sudo $root_dir/deploy/nginx-site.sh   (certificates and the site; DNS must point here)
NEXT
