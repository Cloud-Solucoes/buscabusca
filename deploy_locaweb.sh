#!/bin/bash
# =============================================================================
# Deploy BuscaBusca para Locaweb Shared Hosting
# URL final: https://penseonline.com.br/demos/buscabusca
# =============================================================================

set -e

# Configuracoes
SSH_HOST="187.45.240.32"
SSH_USER="infosolutionwifi1"
SSH_PASS="loca1020"
DEPLOY_PATH="/home/storage/8/c3/8f/infosolutionwifi1/public_html/penseonline/demos/buscabusca"
LOCAL_PATH="/var/www/html/buscabusca"
SSH_OPTS="-o HostKeyAlgorithms=+ssh-rsa -o PubkeyAcceptedKeyTypes=+ssh-rsa -o PubkeyAuthentication=no -o PreferredAuthentications=password -o StrictHostKeyChecking=no"

echo "========================================="
echo " BuscaBusca — Deploy para Locaweb"
echo "========================================="
echo ""

# Verificar sshpass
if ! command -v sshpass &> /dev/null; then
    echo "[ERRO] sshpass nao encontrado. Instale com: sudo apt install sshpass"
    exit 1
fi

# -----------------------------------------------
# 1. Preparar .htaccess para Locaweb
# -----------------------------------------------
echo "[1/5] Preparando .htaccess para Locaweb..."
cp "$LOCAL_PATH/.htaccess.locaweb" "$LOCAL_PATH/.htaccess.deploy"
cp "$LOCAL_PATH/public/.htaccess.locaweb" "$LOCAL_PATH/public/.htaccess.deploy"

# -----------------------------------------------
# 2. Criar diretorio remoto se nao existir
# -----------------------------------------------
echo "[2/5] Criando diretorio remoto..."
sshpass -p "$SSH_PASS" ssh $SSH_OPTS "$SSH_USER@$SSH_HOST" "
    mkdir -p $DEPLOY_PATH/public
    mkdir -p $DEPLOY_PATH/app/model
    mkdir -p $DEPLOY_PATH/app/service
    mkdir -p $DEPLOY_PATH/config
    mkdir -p $DEPLOY_PATH/database
    mkdir -p $DEPLOY_PATH/vendor
"

# -----------------------------------------------
# 3. Enviar arquivos via rsync
# -----------------------------------------------
echo "[3/5] Enviando arquivos via rsync..."
sshpass -p "$SSH_PASS" rsync -avz --delete \
    -e "ssh $SSH_OPTS" \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='test-results' \
    --exclude='.htaccess.locaweb' \
    --exclude='.htaccess.deploy' \
    --exclude='public/.htaccess.locaweb' \
    --exclude='public/.htaccess.deploy' \
    --exclude='playwright.config.js' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='Dockerfile' \
    --exclude='*.md' \
    --exclude='*.txt' \
    --exclude='*.postman_collection.json' \
    --exclude='security_analyzer.php' \
    --exclude='.gitignore' \
    --exclude='deploy_locaweb.sh' \
    "$LOCAL_PATH/" "$SSH_USER@$SSH_HOST:$DEPLOY_PATH/"

# -----------------------------------------------
# 4. Substituir .htaccess pelos de Locaweb
# -----------------------------------------------
echo "[4/5] Configurando .htaccess Locaweb..."
sshpass -p "$SSH_PASS" scp $SSH_OPTS \
    "$LOCAL_PATH/.htaccess.locaweb" \
    "$SSH_USER@$SSH_HOST:$DEPLOY_PATH/.htaccess"

sshpass -p "$SSH_PASS" scp $SSH_OPTS \
    "$LOCAL_PATH/public/.htaccess.locaweb" \
    "$SSH_USER@$SSH_HOST:$DEPLOY_PATH/public/.htaccess"

# -----------------------------------------------
# 5. Ajustar permissoes (SQLite precisa escrita)
# -----------------------------------------------
echo "[5/5] Ajustando permissoes..."
sshpass -p "$SSH_PASS" ssh $SSH_OPTS "$SSH_USER@$SSH_HOST" "
    chmod 775 $DEPLOY_PATH/database
    chmod 664 $DEPLOY_PATH/database/buscabusca.db 2>/dev/null || true
"

# Limpar arquivos temporarios
rm -f "$LOCAL_PATH/.htaccess.deploy" "$LOCAL_PATH/public/.htaccess.deploy"

echo ""
echo "========================================="
echo " Deploy concluido!"
echo "========================================="
echo ""
echo " URL: https://penseonline.com.br/demos/buscabusca/login.html"
echo ""
echo " Teste rapido:"
echo "   curl -s -o /dev/null -w 'HTTP %{http_code}\n' https://penseonline.com.br/demos/buscabusca/login.html"
echo ""
