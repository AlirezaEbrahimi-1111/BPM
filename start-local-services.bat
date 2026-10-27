@echo off
chcp 65001 >nul
rem Local only: build and start the two Go services (go-api :8091, crm-service :8090).
rem Apache (httpd-vhosts.conf) proxies /go/api/ and /crm/api/ to them, same as production.
rem Double-click again after changing Go code: old windows are closed, services are rebuilt.

set ROOT=%~dp0

taskkill /F /IM go-api.exe >nul 2>&1
taskkill /F /IM crm-service.exe >nul 2>&1

echo Building go-api...
pushd "%ROOT%go-api"
go build -o go-api.exe . || (echo go-api build FAILED & popd & pause & exit /b 1)
start "go-api :8091" cmd /k go-api.exe
popd

echo Building crm-service...
pushd "%ROOT%crm-service"
go build -o crm-service.exe . || (echo crm-service build FAILED & popd & pause & exit /b 1)
start "crm-service :8090" cmd /k crm-service.exe
popd

echo.
echo Done. Keep the two service windows open while working locally.
timeout /t 4 >nul
