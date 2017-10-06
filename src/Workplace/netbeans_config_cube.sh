#!/bin/bash

# encourage developer to use the recommended netbeans settings

set -o nounset

if [ -d nbproject ] || [ -d ~/.netbeans/ ] || type netbeans >/dev/null 2>&1
then # netbeans is installed
    nbInstall=1
    installNetbeansSettings () {
        nbUrl=https://github.com/SimonHeimberg/nbproject_4cube
        {
            if [ -d nbproject/.git ]
            then # git repo in place
                git -C nbproject fetch
                git -C nbproject merge --ff-only
            elif [ ! -d nbproject ]
            then # nothing there
                git clone "$nbUrl" nbproject
            else # settings there
                atExit() {
                    [ -d .tmp_nbproject ] && mv -b .tmp_nbproject /tmp/tmp_nbproject
                }
                trap 'atExit' EXIT
                git clone "$nbUrl" .tmp_nbproject &&
                mv -i .tmp_nbproject/.git nbproject && # place repo into config
                rm -r .tmp_nbproject
                git -C nbproject diff --name-only --diff-filter=D | xargs -r -d '\n' git -C nbproject checkout -- # checkout missing files
                git -C nbproject --no-pager diff --exit-code || echo check your netbeans configuration
            fi
        } | sed -e '1 i\\nupdating nbproject ...'
        cp -n nbproject/project.xml.dist nbproject/project.xml # copy project xml if it does not exist
        echo updating nbproject finished
    }
    installNetbeansSettings & # run in background, TODO do not show job id
    disown -h # is not killed on exit of shell
fi

installGitHook () {
    local checkCommitArg pcHook
    checkCommitArg="$1"
    if [ '--nocc' != "$checkCommitArg" ] && [ -d .git ]
    then
        if [ -z "$checkCommitArg" ]
        then
            checkStyle="$(dirname "${BASH_SOURCE[0]}")/../CodeStyle/check-commit-cube.sh"
        else
            checkStyle="$checkCommitArg"
        fi
        pcHook=.git/hooks/pre-commit
        [ -f "$pcHook" ] || [ "$checkStyle" -ef "$pcHook" ] || cp -n "$checkStyle" "$pcHook"
    fi
}

installGitHook "$1"

[ -n "$nbInstall" ] && jobs %% | grep -q Running && sleep 2 # wait a bit to allow jobs to be finished
true # as return value
