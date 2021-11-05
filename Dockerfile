FROM ghcr.io/linuxserver/baseimage-alpine-nginx:stable


RUN \
  echo "**** install runtime packages ****" && \
  apk add --no-cache \
    ffmpeg \
    php7-json


# add local files
COPY root/ /