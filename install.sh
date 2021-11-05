(test -d vod-server-docker && git -C vod-server-docker pull --rebase) || \
  git clone https://github.com/willcodeforfood/vod-server-docker.git \
&& (cd vod-server-docker && docker-compose up --build)