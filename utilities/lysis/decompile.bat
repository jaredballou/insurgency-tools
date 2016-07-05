@echo off
title Lysis Decompiler

echo Welcome to Lysis Decompiler.
echo.

:again
if "%~1" == "" goto done

echo Decompiling %1.
java -jar "Lysis.jar" %1 > %1.sp

shift
goto again

:done
pause