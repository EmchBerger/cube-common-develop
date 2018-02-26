#!/bin/bash
#
# Does various checks on the listed files

set -o errexit -o nounset

thisDir=$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")

# handle args
fileList=$(echo "$@" | xargs -r -n 1 --)

listArgFiles() {
    local arg hasPatt hasQuiet
    local -a grepArgs
    hasPatt=''
    hasQuiet=''
    if [ 0 -eq $# ]
        then return 1
    fi
    for arg
    do
        case "$arg" in
        -G)
            shift # skip next also
            ;;
        --)
            ;;
        --quiet|-q)
            hasQuiet=1
            ;;
        --*)
            grepArgs+=("$arg")
            ;;
        *)
            grepArgs+=(-e "${arg//\*/.*}")
            hasPatt=1
        esac
    done
    if [ -z $hasPatt ]
    then
        grepArgs+=(-e '')
    fi
    if [ "1" = "$hasQuiet" ]
    then
        ! echo "$fileList" | grep --line-regexp --quiet "${grepArgs[@]}"
    else
        echo "$fileList" | grep --line-regexp "${grepArgs[@]}" | tr '\n' '\0'
    fi
}

gitListFiles="listArgFiles" # shellcheck ignore=SC2034

# Redirect output to stderr.
exec 1>&2

whenNoMerge='' # shellcheck ignore=SC2034

# shellcheck source=./src/CodeStyle/check-shared.sh
source "$thisDir/check-shared.sh"

runSharedChecks

setReturnValue
