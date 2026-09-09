FROM hyperf/hyperf:latest
USER root
RUN apk add --no-cache freetds unixodbc \
    && printf '[FreeTDS]\nDriver=/usr/lib/libtdsodbc.so.0\nUsageCount=1\n' > /etc/odbcinst.ini
