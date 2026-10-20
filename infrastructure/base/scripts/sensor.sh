#!/bin/bash
set -e
help() {
echo 'Make requests to the Nginx stub_status endpoint and
pull out metrics'
echo 'for the telemetry service. Refer to the Nginx docs
for details:'
echo 'http://nginx.org/en/docs/http/ngx_http_stub_status_module.html'
}
unhandled() {
local accepts=$(curl -s --fail localhost/nginx-health | awk 'FNR == 3
{print $1}')
local handled=$(curl -s --fail localhost/nginx-health | awk 'FNR == 3
{print $2}')
echo $(expr ${accepts} - ${handled})
