FROM nginx:alpine

COPY docker/nginx.root.conf /etc/nginx/conf.d/default.conf
