#!/bin/bash -ex
cd src
[[ $1 == "css" ]] && sass --style compressed --no-source-map index.scss ../nowboarding.css
[[ $1 == 'js' ]] && npx babel index.js --out-file ../nowboarding.js
cd ..
bga nowboarding