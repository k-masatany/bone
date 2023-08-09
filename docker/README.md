# 開発環境構築手順

## Docker環境構築
※作業ブランチは `main` ( `master` ではない)

1. .envの配置
    ```
    cp .env.example .env
    ```
2. 依存関係のインストール
    ```
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```
3. sail起動
    ```
    ./vendor/bin/sail up -d
    ```
4. PHP Oracleモジュール有効化確認
    1. phpinfoで下記のような出力がでるか
        ```
        ./vendor/bin/sail php -i | grep oci

        /etc/php/8.2/cli/conf.d/oci8.ini
        oci8
        ...
        ```
    - もし有効化されていなければ再ビルド
        ```
        ./vendor/bin/sail stop
        ./vendor/bin/sail build
        ```
5. Oracleのデータ準備

    コンテナ起動時にユーザー作成～DBの復元まで実行します。
    実行内容は`docker/oracle/create-asahim2.sh`を参照してください。

    - スキーマ
        - ASAHI_M2: 開発時に参照するスキーマ
        - ASAHI_TEST: Unitテスト用

    #### DBの再作成方法
    1. Oracleコンテナに入る
        ```
        docker compose exec oracle /bin/bash
        ```
    2. ファイルを削除
        ```
        # 開発用
        rm /opt/oracle/oradata/.ASAHI_M2.created
        # Unitテスト用
        rm /opt/oracle/oradata/.ASAHI_TEST.created
        ```
    3. コンテナ再起動
        ```
        # oracle コンテナから抜ける
        exit
        # 再起動
        ./vendor/bin/sail stop
        ./vendor/bin/sail up -d
        ```
    #### ストアドエラー時のログディレクトリ生成
    必須の作業ではありません。ストアド側で出力しているログを確認したい場合に実行してください。

    oracleコンテナ内で作業。

    ```
    mkdir /home/oracle/logs

    sqlplus ASAHI_M2/password@FREEPDB1
    CREATE DIRECTORY ASAHIM2_SERVER_LOGDIR AS '/home/oracle/logs'
    ```

    Shift_JISで出力されるので、ホスト側にコピーするなりして確認してください。

## アプリケーション設定

1. Laravel設定
    ```
    ./vendor/bin/sail artisan key:generate
    ```
2. DBマイグレーション
    1. PostgreSQL
        ```
        ./vendor/bin/sail artisan migrate
        ```
    2. Oracle  
        ダンプファイルからリストアするのでありません
3. Seed 
    1. PostgreSQL
        ```
        ./vendor/bin/sail artisan db:seed
        ```
    2. Oracle
        ```
        ./vendor/bin/sail artisan db:seed --database=kikan --class=Database\\Seeders\\Kikan\\DatabaseSeeder
        ```

3. http://localhost:8080 へアクセスしてLaravelの画面がでれば成功

## 管理画面設定

1. npm install
    ```
    ./vendor/bin/sail npm i
    ```
2. jwtトークン作成
    ```
    ./vendor/bin/sail artisan jwt:secret
    ```
3. Vite起動
    ```
    ./vendor/bin/sail npm run dev
    ```
4. http://admin.localhost:8080/login へアクセスしてログインページが表示されれば成功

Oracleのマイグレーションについては`database/README.md`を参照してください。


## Cypress (for Windows 11 WSL2, WSLg)

```
# Cypress GUI起動
docker compose -f docker-compose.yml -f docker-compose-cypress.yml run --rm e2e-gui

# eshopテスト
docker compose -f docker-compose.yml -f docker-compose-cypress.yml run --rm e2e-eshop

# adminテスト（ホスト問題があるのでまだうごかない）
docker compose -f docker-compose.yml -f docker-compose-cypress.yml run --rm e2e-admin
```

### WSLgの確認について

こちらを参考に。。
https://blog.mohyo.net/2022/02/11591/


## 基幹側変更分(ストアド・定義変更等）を開発環境・CIに反映する手順

1. コンテナ down
    ```
    ./vendor/bin/sail down
    ```
2. oracle ボリューム削除  ※ ボリューム名は適宜環境毎に調整してください
    ```
    docker bolume rm  asahi-ryokuken-ec_sail-oracle
    ```
3. コンテナ起動  
    ```
    ./vendor/bin/sail up
    ```
    ※ dmpリストアで時間かかります。下記ログがでるまで待ってください。
    ```
    ...
    #########################
    ASAHI_TEST Initialize End!
    #########################
    ```

4. ストアド反映
    SQLDevelopper等でローカル開発環境のOracleに接続して実行。
    ※ SQLDevelopperを使う場合、INSERT文などは自動コミットじゃないので注意。
5. ダンプファイル再作成
    1. Oracleコンテナ内で作業
        ```
        docker compose exec oracle /bin/bash
        # 既存ファイル削除
        rm  /opt/oracle/oradata/FREE/FREEPDB1/kikan_backup_dir/kikan.dmp
        # dmp作成
        expdp ASAHI_M2/password@FREEPDB1 schemas=ASAHI_M2 directory=kikan_backup_dir dumpfile=kikan.dmp logfile=expdp_schema.log
        # git管理下へ移動
        su
        cp /opt/oracle/oradata/FREE/FREEPDB1/kikan_backup_dir/kikan.dmp /home/oracle/app/docker/oracle/kikan.dmp
        ```
6. dmpファイルが差分とし出てくるのでmainリポジトリに反映
7. CI用のイメージ再作成を依頼する。

## コマンド

```
# コンテナ起動
./vendor/bin/sail up -d

# PHPUnit
./vendor/bin/sail test

# pint  (commitされていない変更のあるファイルだけ実行)
./vendor/bin/sail composer pint

## pint (全ファイル対象)
./vendor/bin/sail pint

# phpstan (Larastan)
./vendor/bin/sail composer stan

# id-helper 生成
./vendor/bin/sail composer ide-helper

```

## Devcontainer

Devcontainerで開発する場合の手順。

https://code.visualstudio.com/docs/devcontainers/containers

1. devcontainer.json の準備
    ```
    cp  .devcontainer/devcontainer.json.example .devcontainer/devcontainer.json
    ```
2. VS Codeでプロジェクトルートを開く
3. Reopen in container..と出るので「YES」
    ※ 手作業で実行する場合
    1. Ctrl + Shift + P でコマンドパレット
    2. Dev Containers: Reopen in container を選択


### ビルドでエラーになる場合の対処

- `groupadd --force -g $(id -g) sail` でエラーになる場合

    ```
    => ERROR [ 9/14] RUN groupadd --force -g $(id -g) sail                                                            0.6s
    ------
    > [ 9/14] RUN groupadd --force -g $(id -g) sail:
    #0 0.617 groupadd: invalid group ID '$(id'
    ------
    failed to solve: executor failed running [/bin/sh -c groupadd --force -g $WWWGROUP sail]: exit code: 3
    ```
    1. uid,gidを調べる
        ```
        id
        uid=0(root) gid=0(root) groups=0(root),1000(docker)
        ```
    2. .envにuid,gidを設定
        ```
        WWWUSER=0
        WWWGROUP=0
        ```
