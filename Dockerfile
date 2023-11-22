FROM ghcr.io/linuxserver/baseimage-alpine-nginx:3.18-version-d20967b0


RUN \
  echo "**** install runtime packages ****" && \
  apk add --no-cache \
    ffmpeg \
    libavif-apps \
    intel-media-driver


# Intel Quicksync support
RUN apk add onevpl-intel-gpu --repository=https://dl-cdn.alpinelinux.org/alpine/edge/testing

# Maybe also..
# apk add mesa-va-gallium libva-intel-driver
# and maybe even
# apk add linux-firmware-i915

# add local files
COPY root/ /