#!/bin/bash
#
# Does various checks on the listed files

set -o errexit -o nounset

thisDir=$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")

# handle args
fileList=$(echo "$@" | xargs -r -n 1 --)

if [ -z "$fileList" ]
then
    echo error: no file given as argument >&2
    exit 1
fi

listArgFiles() {
    local arg hasPatt hasQuiet
    local -a grepArgs
    hasPatt=''
    hasQuiet=''
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
        grepArgs+=(-e '.*') # match all
    fi
    if [ "1" = "$hasQuiet" ]
    then
        ! echo "$fileList" | grep --line-regexp --quiet "${grepArgs[@]}"
    else
        echo "$fileList" | grep --line-regexp "${grepArgs[@]}" | tr '\n' '\0'
    fi
}

gitListFiles="listArgFiles"
true "$gitListFiles" # used in check-shared.sh

# Redirect output to stderr.
exec 1>&2

whenNoMerge=''
true "$whenNoMerge" # used in check-shared.sh

# shellcheck source=./src/CodeStyle/check-shared.sh
source "$thisDir/check-shared.sh"

if listArgFiles | runXArgs0 -- file -- | grep ': *data$'
then
    echo 'above files are binary, is this expected?'
    showWarning
fi

runSharedChecks

setReturnValue
