#!/bin/sh
#
# finds all (potential) usages of twig templates and checks if they exist
#     after it checks if all existing templates are used

set -o nounset -o errexit

atExit () {
    rc=$?
    if [ 0 -ne $rc ]
    then
        printf '  ** failed with %s' "$rc"
        [ -n "$1" ] && [ 1 != "$1" ] && printf ' on line %s' "$1" # LINENO is 1 often, which is always wrong
        printf '\n'
    fi >&2
    if [ -n "${usedTemplatesFile:-}" ]
    then
        rm "$usedTemplatesFile" || true
    fi
}

trap 'atExit "${LINENO:-}"' EXIT

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

if [ -n "$noArgs" ]
then
    usedTemplatesFile=$(mktemp --tmpdir twig-used-templates-XXXX)
else
    usedTemplatesFile=
fi

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
        elif [ -n "$usedTemplatesFile" ]
        then
            echo "templates/$template" >> "$usedTemplatesFile"
        fi
    done
done

printf '%s\n' "$@" | grep ^src/ | xargs -r -d '\n' -- grep -r --include=*.php --line-number -e '@Template()' | sed 's/^/  unchecked in /'

if [ -n "$usedTemplatesFile" ]
then
    find templates/ -name 'bundles' -type d -prune -o -name '*\.twig' -print | while read -r existingTemplate
    do
        if ! grep -q --fixed-strings --line-regexp -e "$existingTemplate" "$usedTemplatesFile"
        then
            echo "probably unused: $existingTemplate"
        fi
    done
fi
