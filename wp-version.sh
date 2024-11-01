#!/bin/bash -e

# NOTE: requires GNU sed
# on mac: `brew install gnu-sed`

plugin_file="woocommerce-prismappio.php"
search='^( \* Version:) (.+)$'

if [ "$1" == "" ]; then
    # print current version
    cat $plugin_file | grep -E "$search" | awk '{print $3}'
else
    # update current version
    version=$1
    replace="\1 ${version}"

    if [ `uname` == "Darwin" ]; then
      sed="gsed"
    else
      sed="sed"
    fi

    $sed -i -E "s/${search}/${replace}/" $plugin_file
fi
