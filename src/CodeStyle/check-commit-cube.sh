#!/bin/bash
#
# to enable this hook, run
#  cp -bi src/CodeStyle/check-commit-cube.sh .git/hooks/pre-commit
#
# Does various checks on the files to be checked in

thisDir=$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")
if [ -f "$thisDir/check-shared.sh" ]
then
    sharedDir="$thisDir"
elif [ -f vendor/cubetools/cube-common-develop/src/CodeStyle/check-shared.sh ]
then
    sharedDir=vendor/cubetools/cube-common-develop/src/CodeStyle
elif [ -f src/CodeStyle/check-shared.sh ]
then # in cube-common-develop
    sharedDir=src/CodeStyle
elif [ -f vendor/cubetools/cube-common-develop/src/CodeStyle/check-commit-cube.sh ]
then # old cube-common-develop, fallback on downgrade of cube-common-develop
    source vendor/cubetools/cube-common-develop/src/CodeStyle/check-commit-cube.sh
    exit
else
    sharedDir="$thisDir"
    # shellcheck source=./src/CodeStyle/check-shared.sh
    source "$sharedDir/check-shared.sh" # to trigger error
fi

checkAuthor() {
    if echo "$GIT_AUTHOR_EMAIL" | grep "$(hostname -f)" || echo "$GIT_AUTHOR_EMAIL" | grep "$(hostname)"
    then
        echo "really commit with email '$GIT_AUTHOR_EMAIL'?"
        showWarning
    fi
}
checkAuthor

preInitialCommit=4b825dc642cb6eb9a060e54bf8d69288fbee4904

# handle args
cachedDiff=--cached
against=
if [ "$1" == '--changed' ]
then
    cachedDiff=
elif [[ "$1" == --* ]]
then
    echo 'basic checks on stashed (or committed) changes'
    echo
    echo usage: "$0" '[--changed|REV]'
    exit
elif [ -n "$1" ]
then
    if [ "${1%..}" != "$1" ]
    then
        against="$(git log --reverse --format=format:%H "$1" -- | head -n 1)"
    else
        against=$(git rev-parse --verify "$1")
    fi
    [ -z "$against" ] && exit 64
    echo checking against revision "$against"
fi

if [ -n "$against" ]
then
    against="$against~" # since parent of it
elif git rev-parse --verify HEAD >/dev/null 2>&1
then
    against=HEAD
else
    # Initial commit: diff against an empty tree object
    against="$preInitialCommit"
fi
if [ HEAD = $against ]
then
    mergeAgainst=
else
    mergeAgainst=$against
fi

[ -f .git/MERGE_HEAD ] && whenNoMerge=true || whenNoMerge='' #runs "true cmd" when in a merge, the cmd else
true $whenNoMerge # is used in check-shared.sh



gitListFiles="git diff $cachedDiff --name-only --diff-filter ACMTUB -z $against"

# shellcheck source=./src/CodeStyle/check-shared.sh
source "$sharedDir/check-shared.sh"

showMainCommand

[ -z "${xArgs0:?xArgs0 missing}" ] && xArgs0=valueIsCheckedNow

checkScriptChanged() {
    #check if script has changed
    local pathInRepo
    pathInRepo=src/CodeStyle/check-commit-cube.sh

    if [ -n "$ccOrigPath" ]
        then true # is set from calling script
    elif [ -f vendor/cubetools/cube-common-develop/$pathInRepo ]
        then ccOrigPath=vendor/cubetools/cube-common-develop/$pathInRepo
    elif [ -f $pathInRepo ]
        then ccOrigPath=$pathInRepo
    else
        echo can not check if script is current, set ccOrigPath in your main check commit script
        showWarning
        return $?
    fi
    [ -z "$ccScriptPath" ] && ccScriptPath="${BASH_SOURCE[0]}" # set to this scripts path if not set
    if [ "$ccScriptPath" -ef "$ccOrigPath" ]
    then
        true linkToOrig # points to ccOrigPath
    elif [ "${ccScriptPath:0:1}" != . ]
    then
        echo some failure, wrong dest found, please set ccScriptPath to the hook script >&2
        showWarning
    elif [ "$ccOrigPath" -ot "$ccScriptPath" ]
        then true # current one is not older
    elif ! diff -q "$ccOrigPath" "$ccScriptPath" > /dev/null
    then
        # different content
        echo "update the pre-commit script by running cp -b $ccOrigPath '$ccScriptPath'"
        showWarning
    else # same content but older
        touch -r "$ccOrigPath" "$ccScriptPath" #update timestamp
    fi
}
$gitListFiles --quiet || checkScriptChanged # only when files to check

# Note that the use of brackets around a tr range is ok here, (it's
# even required, for portability to Solaris 10's /usr/bin/tr), since
# the square bracket bytes happen to fall in the designated range.
if test "$(git diff $cachedDiff --name-only --diff-filter=A -z "$against" |
      LC_ALL=C tr -d '[ -~]\0' | wc -c)" != 0
then
    cat <<\EOF
Error: Attempt to add a non-ASCII file name.

This can cause problems if you want to work with people on other platforms.

To be portable it is advisable to rename the file.
EOF
    showWarning
fi

set -o errexit -o nounset

# If there are whitespace errors, print the offending file names and warn.
git diff --check $cachedDiff $mergeAgainst -- || showWarning

# check for files with exec bit new set
if git diff $cachedDiff "$against" --raw -- | grep ':...[^7].. ...7..'
then
        echo 'above files with EXEC bit set now, is this expected?'
        echo 'if not, run $ chmod a-x $''(git diff --cached --name-only)'
        showWarning
fi

checkNumstatForBinaryFile() {
    # set --cached or 2nd commit, both does not work
    if [ -z "$cachedDiff" ]
    then
        xargs -r -d '\n' -- git diff --numstat "$preInitialCommit" "$against" --
    else
        xargs -r -d '\n' -- git diff --numstat "$cachedDiff" "$preInitialCommit" --
    fi
}
# check for files with binary data
if git diff $cachedDiff "$against" --numstat | grep '^-' | cut -f 3- | checkNumstatForBinaryFile | grep '^-'
then
    echo 'above files are binary, is this expected?'
    showWarning
fi

# warn on unwanted terms

findUnwantedTerms () {
    # args: file-pattern, invalid-pattern
    local avoidMsg avoidColors filePatt invPatts r
    avoidMsg='= avoid introducing what is colored above ='
    avoidColors='ms=01;33'
    filePatt="$1"
    invPatts="$2"
    git diff $cachedDiff $mergeAgainst -G "$invPatts" --color -- "$filePatt" |
        grep -v -E '^[^-+ ]{0,9}-.*('"$invPatts)" | ### filter out matches on "- " line, respecting gits coloring
        GREP_COLORS="$avoidColors" grep --color=always -C 16 -E "$invPatts"
    r=$?
    if [ 0 -eq $r ]
    then
        echo "$avoidMsg" | GREP_COLORS="$avoidColors" grep --color=always colored
    fi
    return $r
}
invPatts="\(array\).*json_decode|new .*Filesystem\(\)|->add\([^,]*, *['\"][^ ,:]*|->add\([^,]*, new |createForm\( *new  "
invPatts="$invPatts|(^| )dump\(|\\$\\$|->get\([^)]*::[^)]*\)|->get\([^\)]*\\\\[^\)]*\)"
invPatts="$invPatts|[Aa]uto[- ]?generated.*please|@[a-zA-Z]* type\b"
if findUnwantedTerms '*.php' "$invPatts"
then
    cat <<'TO_HERE'
use this:
  * json_decode(xxx, true)           instead of (array) json_decode(xxx)
  * typehint Filesystem              instead of new Filesystem, only in case typehinting is around
  * ->add('name', TextType::class    instead of ->add('add', 'text' when creating forms (and ChoiceType, DateType, ...)
  * SomeType::class                  instead of new SomeType() in ->add( and ->createForm('
  * remove debugging                 dump(...) breaks non-debug run
  * ${$name_of_var}                  instead of $$name_of_var (in case you really want this)
  * function __construct(Class $var  instead of ->get(ClassName) in services (auto wiring)
  * function xxAction(Class $var, .. instead of ->get(ClassName) in Controllers (auto wiring)
  * write real doc (or delete)       no default doc (like auto-generated, @param type)
TO_HERE
    showWarning
fi
invPatts="</input>|</br>|replace\(.*n.*<br.*\)|\{% *dump |\{\{[^}]dump\("
if findUnwantedTerms '*.htm*' "$invPatts"
then
    cat <<'TO_HERE'
use this:
  * <input .../>            instead of <input ...></input> because input is standalone. Attr value="xx" is for values.
  * <div>...</div> or <br>  instead of </br>, an unexisting tag
  * |nl2br                  instead of |replace({'\n', '<br>'}) (nl2br does NOT need {% autoescape false %} or |raw )
  * remove debugging        {% dump ... %} and {{ dump( ... ) do not work on non-debug run
TO_HERE
    showWarning
fi

invPatts="public: true"
if findUnwantedTerms 'app/config/services.yml' "$invPatts"
then
    cat <<'TO_HERE'
  * do NOT make services public, use auto wiring instead
    - function __construct(Class $var  instead of ->get(ClassName) in services
    - function xxAction(Class $var, .. instead of ->get(ClassName) in Controllers
TO_HERE
    showWarning
fi

invPatts="\.format\(.[DMY]"
if findUnwantedTerms '*.twig' "$invPatts"
then
    cat <<'TO_HERE'
  * do not use dateVar.format(...), use dateVar|showDate (in pa) or dateVar|date(...)
       .format(...) crashes on null, |showDate shows empty, |date(...) shows now
TO_HERE
    showWarning
fi

# check files to commit for local changes
if [ -n "$cachedDiff" ] && ! $gitListFiles | $xArgs0 git diff-files --name-only --exit-code --
then
    echo 'above files for commit also modified in working directory'
    echo 'this may produce wrong results'
    showWarning
    ## or stash changes and unstash at end, see http://daurnimator.com/post/134519891749/testing-pre-commit-with-git
fi

runSharedChecks

if [ -f composer.lock ] && [ -z "$(git ls-files composer.lock)" ] && [ $(( $(date +%s)-$(date -r composer.lock +%s) )) -gt 864000 ]
then
    printf '\n  untracked composer.lock is older than 10 days, run composer update\n\n' | grep --color -e '' -e 'composer .*'
fi

setReturnValue
