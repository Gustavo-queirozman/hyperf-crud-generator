FROM hyperf/hyperf:latest
USER root
RUN apk add --no-cache freetds unixodbc \
    && printf '[FreeTDS]\nDriver=/usr/lib/libtdsodbc.so\nUsageCount=1\n' > /etc/odbcinst.ini
