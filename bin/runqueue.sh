#!/bin/sh

trap "trap - SIGTERM && kill -- -$$" SIGINT SIGTERM EXIT

php bin/console messenger:consume async_priority_high $1 &
php bin/console messenger:consume async_priority_medium $1 &
php bin/console messenger:consume async_priority_low $1
