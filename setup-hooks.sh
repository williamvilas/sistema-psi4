#!/bin/bash

git config core.hooksPath .githooks
chmod +x .githooks/*

echo "Git hooks ativados (.githooks)."
