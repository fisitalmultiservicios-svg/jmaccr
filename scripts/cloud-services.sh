#!/bin/bash
set -euo pipefail
# Cloud development only; never use this script on cPanel.
source /workspace/toolchain/activate
jmac_mysql=/workspace/toolchain/root/usr/bin/mariadb
jmac_server=/workspace/toolchain/root/usr/sbin/mariadbd
if ! "$jmac_mysql" --no-defaults --socket=/workspace/toolchain/mysql.sock -e 'SELECT 1' >/dev/null 2>&1; then
  if [ ! -d /workspace/toolchain/mysql/mysql ]; then
    /workspace/toolchain/root/usr/bin/mariadb-install-db --no-defaults --basedir=/workspace/toolchain/root/usr --datadir=/workspace/toolchain/mysql --auth-root-authentication-method=socket --skip-test-db > /workspace/toolchain/mysql-init.log 2>&1
  fi
  nohup "$jmac_server" --no-defaults --basedir=/workspace/toolchain/root/usr --datadir=/workspace/toolchain/mysql --socket=/workspace/toolchain/mysql.sock --pid-file=/workspace/toolchain/mysql.pid --bind-address=127.0.0.1 --port=3307 --skip-log-bin > /workspace/toolchain/mysql-server.log 2>&1 &
  for attempt in $(seq 1 20); do
    if "$jmac_mysql" --no-defaults --socket=/workspace/toolchain/mysql.sock -e 'SELECT 1' >/dev/null 2>&1; then break; fi
    sleep 1
  done
fi
"$jmac_mysql" --no-defaults --socket=/workspace/toolchain/mysql.sock -e 'CREATE DATABASE IF NOT EXISTS jmac CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS jmac_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
