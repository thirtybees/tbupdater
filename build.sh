#!/usr/bin/env bash

FILES=("index.php")
FILES+=("logo.gif")
FILES+=("logo.png")
FILES+=("nocaptcharecaptcha.php")
FILES+=("Readme.md")
FILES+=("classes/**")
FILES+=("controllers/**")
FILES+=("lib/**")
FILES+=("optionaloverride/**")
FILES+=("templates/**")
FILES+=("translations/**")
FILES+=("views/**")

CWD_BASENAME=${PWD##*/}

MODULE_VERSION="$(sed -ne "s/\\\$this->version *= *['\"]\([^'\"]*\)['\"] *;.*/\1/p" ${CWD_BASENAME}.php)"
MODULE_VERSION=${MODULE_VERSION//[[:space:]]}
ZIP_FILE="${CWD_BASENAME}/${CWD_BASENAME}-v${MODULE_VERSION}.zip"

echo "Going to zip ${CWD_BASENAME} version ${MODULE_VERSION}"

cd ..
for E in "${FILES[@]}"; do
  find ${CWD_BASENAME}/${E}  -type f -exec zip -9 ${ZIP_FILE} {} \;
done
