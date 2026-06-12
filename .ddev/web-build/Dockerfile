RUN apt-get update -qq && \
    apt-get install -y -qq --no-install-recommends wget ca-certificates && \
    wget -q https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb && \
    apt-get install -y -qq --fix-broken ./google-chrome-stable_current_amd64.deb && \
    rm google-chrome-stable_current_amd64.deb && \
    rm -rf /var/lib/apt/lists/*
