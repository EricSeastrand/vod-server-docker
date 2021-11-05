FROM linuxserver/nginx:latest

COPY ./build/nginx-configs /config
COPY ./build/vod-server-webapp /vod-server