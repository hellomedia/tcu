#!/bin/sh


# Deploy to staging:
#
#     $ TRACK=staging bin/deploy.sh
#
# Deploy to prod:
#
#     $ bin/deploy.sh
#
# Maintenance:
#
#     $ bin/console app:maintenance:start
#     $ bin/console app:maintenance:stop
#
#  NB: Maintenance might not survive deployment, as the maintenance.php file might be overwritten.
#      If you need maintenance to survive deployment, might need double checking.
#
# If deployment breaks site, rollback:
#
#     $ ssh deploy@taro.hellokot.be doas /data/tcu/activate-release.sh live <YEAR><MONTH><DATE>-<HOUR><MINUTE><SECOND>
#
#     OR
#     $ cd /www/tcu/prod
#     $ rm current-release  ( without trailing slash !)
#     $ ln -s releases/<previous-working-release> current-release
#     $ service php_fpm restart


set -e
set -x

BRANCH=main
HOST=taro.hellokot.be
USER=deploy
TRACK=${TRACK:-live}
PREFIX=/data/tcu/www/$TRACK
SSH="ssh"
RELEASE_NAME=$(date +"%Y%m%d-%H%M%S")
LOCAL_REPO=$(git rev-parse --show-toplevel || pwd)
REPO=git@github.com:hellomedia/tcu
DEST=$PREFIX/releases/$RELEASE_NAME
TMP_REPO=$(TMPDIR=$(pwd) mktemp -d -t tcu-deploy.XXXXX)

# build inside docker
COMMAND_PREFIX="docker compose -f ../docker-compose.yaml exec --user=www-data php sh -c"
BUILD_DIR=/data/www/$(basename $TMP_REPO)

# tailwind binary
# ATTN: line below expects 1 version folder. When we change the binary version, the old folder should be deleted.
TAILWIND_BINARY_VERSION_DIR=$(basename $(dirname "$(realpath /home/nicolas/projects/tcu/var/tailwind/*/tailwindcss-linux-x64)"))
TAILWIND_BINARY=/data/www/var/tailwind/$TAILWIND_BINARY_VERSION_DIR/tailwindcss-linux-x64

if [ $1 = "--no-docker" ]; then
    COMMAND_PREFIX='sh -c'
    BUILD_DIR=$TMP_REPO
fi

# mirror COMPOSER_INSTALL_ARGS on server for optimizing rsync
# live ==> no dev dependencies
# staging ==> all dependencies - since we load DoctrineFixturesBundle and WebProfilerBundle in bundles.php
case $TRACK in
    live)
    BUILD_APP_ENV=prod
    COMPOSER_INSTALL_ARGS="--no-scripts --no-dev"
    ;;
    staging)
    BUILD_APP_ENV=dev
    COMPOSER_INSTALL_ARGS="--no-scripts"
    ;;
esac

trap cleanup INT TERM EXIT

cleanup () {
    trap "" INT TERM EXIT
    rm -rf "$TMP_REPO"
    kill -- -$$
}

local_clone () {
    if [ git rev-parse --show-toplevel ]; then
        git clone "$REPO" "$TMP_REPO" --depth=1 "--branch=$BRANCH" "--reference=$LOCAL_REPO"
    else
        git clone "$REPO" "$TMP_REPO" --depth=1 "--branch=$BRANCH"
    fi
    (
        cd $TMP_REPO
        if [ $TRACK = "live" ]; then
            git tag -a "release-$RELEASE_NAME" -m "tag live release $RELEASE_NAME"
        fi
    )
}

local_build () {
    $COMMAND_PREFIX "cd $BUILD_DIR && ./composer.phar install $COMPOSER_INSTALL_ARGS"

    # APP_ENV in .env.local must be coherent with composer install args ( prod ==> --no-dev  , dev ==> x )
    cp $LOCAL_REPO/.env.local ./

    $COMMAND_PREFIX "cd $BUILD_DIR && APP_ENV=$BUILD_APP_ENV bin/console importmap:install"

    # copy tailwind binary into local build to avoid heavy download
    $COMMAND_PREFIX "cd $BUILD_DIR && mkdir -p var/tailwind/$TAILWIND_BINARY_VERSION_DIR"
    $COMMAND_PREFIX "cd $BUILD_DIR && cp $TAILWIND_BINARY var/tailwind/$TAILWIND_BINARY_VERSION_DIR"

    $COMMAND_PREFIX "cd $BUILD_DIR && APP_ENV=$BUILD_APP_ENV bin/console tailwind:build --minify"

    $COMMAND_PREFIX "cd $BUILD_DIR && APP_ENV=$BUILD_APP_ENV bin/console asset-map:compile"

    $COMMAND_PREFIX "cd $BUILD_DIR && APP_ENV=$BUILD_APP_ENV bin/console cache:warmup"
}

remote_init_dir () {
    $SSH "$USER@$HOST" "sh -c \"mkdir -p $DEST && chmod 755 $DEST && ( cd $PREFIX/current-release && find ./ \! -path './var/cache/*' | pax -pp -rw $DEST ) || true\""
}

rsync_local () {
    rsync --rsh="$SSH" -av \
        "$@" \
        --exclude /.git \
        --exclude /.hg \
        --exclude /var/cache \
        --exclude /.env\*local\* \
        ./ "$USER@$HOST:$DEST/"
}

remote_activate () {
    if ! $SSH -t "$USER@$HOST" "doas /data/tcu/activate-release.sh $TRACK \"$(basename $DEST)\""; then
        set +x
        echo -e "\033[41m\033[97m"
        echo ""
        echo " Failed"
        echo -e "\033[0m";
        set -x
        exit 1;
    fi
}

local_clone
remote_init_dir
wait
(
    cd "$TMP_REPO"

    rsync_local
    local_build
    wait
    rsync_local --delete
    remote_activate
)
if [ $TRACK = "live" ]; then
    (cd "$TMP_REPO" && git push origin --tags)
fi
