FROM ghcr.io/linuxserver/baseimage-alpine-nginx:2021.11.04


RUN \
  echo "**** install runtime packages ****" && \
  apk add --no-cache \
    ffmpeg \
    php7-json


# add local files
COPY root/ /