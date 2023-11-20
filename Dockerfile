FROM ghcr.io/linuxserver/baseimage-alpine-nginx:3.18-version-d20967b0


RUN \
  echo "**** install runtime packages ****" && \
  apk add --no-cache \
    ffmpeg


# add local files
COPY root/ /