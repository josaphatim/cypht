#!/bin/bash

PYTHON=$(command -v python3)
rm -rf __pycache__/

#inline_msg.py keyboard_shortcuts.py
for suite in keyboard_shortcuts.py
do
    export TEST_SUITE="$suite"
    "$PYTHON" -u ./$suite
    if [ $? -ne 0 ]; then
        exit 1
    fi
done
