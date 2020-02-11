#!/bin/sh
#
# finds all (potential) usages of twig templates and checks if they exist

set -o nounset -o errexit

atExitReport () {
    rc=$?
    if [ 0 -ne $rc ]
    then
        printf '  ** failed with %s' "$rc"
        [ -n "$1" ] && printf ' on line %s' "$1"
        printf '\n'
    fi >&2
}

trap 'atExitReport "${LINENO:-}"' EXIT

noArgs=
if [ 0 -eq $# ]
then
    set -- src/ templates/
    noArgs=1
fi

colorMatch() {
    grep --color=auto -e '' -e "[^:/ ]$1."
}

matchD='"[^"][^"]*\.twig"'
matchS="'[^'][^']*\\.twig'"

{
    grep -r --line-number '--include=*.php' '--include=*.twig' -e "$matchD" -e "$matchS" "$@"
    if [ -n "$noArgs" ]
    then
        find config/ app/config/ -maxdepth 0 -print0 | xargs -0 -r -- grep -r --line-number '--include=*.yml' '--include=*.yaml' -e '\.twig($|[ "'"'])"
    fi
} | while read -r grepMatch
do
    printf %s "$grepMatch" | grep -o -e "$matchD" -e "$matchS" -e '\b[^ ]*\.twig($|[ ])' | sed -e 's/^["'"']//" -e 's/[ "'"']$//" |
      # explain above:                   # get match only                                         # strip quotes (" or ') at start and end (or space at end)
      while read -r template
    do
        if printf %s "$template" | grep -q ^@ # starts with @
        then
            printf '  unchecked in %s\n' "$grepMatch" | colorMatch "$template"
            continue
        fi
        if ! [ -f "templates/$template" ]
        then
            printf 'not found in %s\n' "$grepMatch" | colorMatch "$template"
        fi
    done
done

printf '%s\n' "$@" | grep ^src/ | xargs -r -d '\n' -- grep -r --include=*.php --line-number -e '@Template()' | sed 's/^/  unchecked in /'
