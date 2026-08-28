@echo off
REM Publica o trabalho local no GitHub (o Plesk depois puxa e faz deploy).
REM Uso:  publicar.bat "mensagem do commit"
setlocal

cd /d "%~dp0"

if "%~1"=="" (
    set "MENSAGEM=wip: alteracoes locais"
) else (
    set "MENSAGEM=%~1"
)

echo.
echo === Estado atual ===
git status --short
echo.

set /p CONFIRMA="Committar tudo isto e enviar para o GitHub? [s/N] "
if /i not "%CONFIRMA%"=="s" (
    echo Cancelado.
    exit /b 1
)

echo.
echo === A committar ===
git add -A
git commit -m "%MENSAGEM%"

echo.
echo === A enviar ===
git push origin main
if errorlevel 1 (
    echo.
    echo O push falhou. Ve a mensagem acima.
    exit /b 1
)

echo.
echo Enviado. Agora no Plesk: Git ^> Pull now ^(ou Deploy now^),
echo e depois no servidor: bash deploy.sh
echo.
endlocal
