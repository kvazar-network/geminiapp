# KVAZAR geminiapp

[KevaCoin](https://github.com/kevacoin-project/) Explorer for [Gemini Protocol](https://geminiprotocol.net/)

## Examples

* `gemini://[301:23b4:991a:634d::db]` - [Yggdrasil](https://github.com/yggdrasil-network/)
  * `gemini://kvazar.ygg` - [Alfis DNS](https://github.com/Revertron/Alfis)
  * `gemini://kvazar.duckdns.org` - Clearnet

## Install

1. `wget https://repo.manticoresearch.com/manticore-repo.noarch.deb`
2. `dpkg -i manticore-repo.noarch.deb`
3. `apt update`
4. `apt install git composer memcached manticore manticore-extra php-fpm php-mysql php-mbstring`
5. `git clone https://github.com/kvazar-network/geminiapp.git`
6. `cd geminiapp`
7. `composer update`

## Setup

1. `cd geminiapp`
2. `mkdir host/127.0.0.1`
3. `cp example/config.json host/127.0.0.1/config.json`
4. `cd host/127.0.0.1`
5. `openssl req -x509 -newkey rsa:4096 -keyout key.rsa -out cert.pem -days 365 -nodes -subj "/CN=127.0.0.1"`

## Index

To update index, use [crawler](https://github.com/kvazar-network/crawler)

## Launch

`php src/server.php 127.0.0.1`

## Update

1. `cd geminiapp`
2. `git pull` - get latest codebase from this repository
3. `composer update` - update vendor libraries