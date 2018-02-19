#!/bin/bash
#
# Does various checks on the listed files

set -o errexit -o nounset

thisDir=$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")

# handle args
fileList=$(echo "$@" | xargs -r -n 1 --)

listArgFiles() {
    local arg hasPatt
    local -a grepArgs
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
    echo "$fileList" | grep --line-regexp "${grepArgs[@]}" | tr '\n' '\0'
}

gitListFiles="listArgFiles" # shellcheck ignore=SC2034

# Redirect output to stderr.
exec 1>&2

whenNoMerge='' # shellcheck ignore=SC2034

# shellcheck source=./src/CodeStyle/check-shared.sh
source "$thisDir/check-shared.sh"

runSharedChecks

setReturnValue
