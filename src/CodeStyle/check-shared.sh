# sourced with /bin/bash
# shellcheck shell=bash
#
# shared code for check-*.sh scripts

retVal=0

[ -z "${gitListFiles:?gitListFiles must be set}" ] && gitListFiles=valueIsCheckedNow
[ -z "${whenNoMerge-}" ] && whenNoMerge=''

showWarning() {
    local AW FLUSH
    if [ -n "${REPORTONLY:-}" ]
    then
        retVal=1
        echo '--------- continueing check ---------'
        return
    fi
    echo -n '  continue anyway with c, abort with a: '
    while true
    do
        read -r -n 1 AW < /dev/tty || AW=ttyError
        case $AW in
        c)
            echo
            return
        ;;
        a|q|ttyError)
            echo '  Abort'
            exit 2
        ;;
        esac
        read -r -t 1 FLUSH || true < /dev/tty # flush input
        true "$FLUSH" # is a dummy variable
        echo -n ' [ca]? '
    done
}
warnWhenMissing () {
    local lastRet=$?
    if [ 127 -eq $lastRet ] # not found error
    then
        showWarning
        return $?
    fi
    return $lastRet
}

cmdPrefix="   $(tput setaf 6)# running:$(tput setaf 7)" || true
showCommandXArg() {
    local append=$1
    shift
    [ "--" = "$1" ] && shift # remove leading --
    sed -z -e '1 {' -e h -e "/./ s@^@$cmdPrefix ${*//@/?} @" -e '/./ s/$/ '"$append"'...\n/' -e 'w /dev/stderr' -e x -e '}'
}

showCommand() {
    echo "$cmdPrefix" "$@" >&2
}

showMainCommand() {
    if [ -n "${GIT_AUTHOR_DATE:-}" ]
    then
        findComposer
        echo "${cmdPrefix#  }" "$($composerCmd config bin-dir)"/check-commit-cube.sh
        echo
    fi
}

xArgs0cmd='xargs -0 -r'
runXArgs0() {
    showCommandXArg '' "$@" | $xArgs0cmd "$@"
}
runXArgs0n1() {
    showCommandXArg '; ' "$@" | $xArgs0cmd -n 1 -P "$(nproc)" "$@"
}
xArgs0=runXArgs0
xArgs0n1=runXArgs0n1

runValidPhp() {
    $gitListFiles -- '*.php' | $xArgs0n1 -- php -l
}

getInVendorBin () {
    local binDir
    binDir=vendor/bin
    if [ ! -f "$binDir/$1" ] && [ -f "bin/$1" ]
    then
        binDir=bin
    fi
    echo "$binDir/$1"
}

[ -z "${phpBinary:-}" ] && phpBinary=c
findPhpBinary () {
    if [ c != "$phpBinary" ]
    then
        true
    elif ls ./???/cache/ccPhpVersion >/dev/null 2>&1
    then
        phpBinary="$(type -p "$(cat ./???/cache/ccPhpVersion | head -n 1)")"
    else
        phpBinary=''
    fi
}

runPhpUnit () {
    if [ -z "${phpUnit:-}" ]
    then
        phpUnit=$(getInVendorBin phpunit)
        if [ ! -f "$phpUnit" ]
        then
            phpUnit='phpunit'
        fi
    fi

    showCommand phpunit "$@"
    SYMFONY_DEPRECATIONS_HELPER=disabled "$phpUnit" "$@"
}

checkTranslations () {
    local transTest
    transTest="$(find tests/ src/ vendor/cubetools/ -type f -name 'Translation*Test.php' -print -quit)"
    if [ ! -f "$transTest" ]
    then
        echo can not check translations, test 'Translation*Test.php' is missing.
        showWarning
        return $?
    fi

    runPhpUnit "$transTest" || warnWhenMissing
}

runCheckTranslation() {
    $gitListFiles --quiet  -- '*.xliff' '*.xlf' || checkTranslations
}

findSyConsole () {
    if [ -z "${syConsole:-}" ]
    then
        syConsole=bin/console
        if [ -f "$syConsole" ]
            then true # OK
        elif [ -f app/console ]
            then syConsole=app/console
        else
            syConsoleError="console not available to run $syConsole"
        fi
    fi

    if [ -n "${syConsoleError:-}" ]
        then return 127
    fi
}

syConsoleRun() {
    if ! findSyConsole
    then
        echo "$syConsoleError" "$@"
        return 127 # not found error
    fi
    findPhpBinary
    showCommand "$syConsole" "$@"
    $phpBinary "$syConsole" "$@"
}
syConsoleXargs () {
    local oneChar
    findPhpBinary
    if ! findSyConsole  && read -r -N 1 oneChar
    then # no console but input, trigger error
        { printf "%s" "$oneChar"; cat; } | $xArgs0 -- echo "$syConsoleError" "$@"
        return 127
    fi
    $xArgs0 -- $phpBinary "$syConsole" "$@"
}
syConsoleXargsN1 () {
    local oneChar
    findPhpBinary
    if ! findSyConsole && read -r -N 1 oneChar
    then # no console but input, trigger error listing files in one command
        { printf "%s" "$oneChar"; cat; } | $xArgs0 -- echo "$syConsoleError" "$@" for
        return 127
    fi
    $xArgs0n1 -- $phpBinary "$syConsole" "$@"
}

runCheckDatabase() {
    #check database (when an annotation or a variable changed in an entity)
    $gitListFiles --quiet -G ' @|(protected|public|private) +\$\w' -- '*/Entity/*.php' ||
        syConsoleRun doctrine:schema:validate || showWarning
}


runCheckTwig() {
    $gitListFiles -- '*.twig' | syConsoleXargs lint:twig || warnWhenMissing
}

runCheckYaml() {
    $gitListFiles -- '*.yml' '*.yaml' '*.neon' | syConsoleXargsN1 lint:yaml -- || warnWhenMissing
}

findComposer() {
        local checkDir
        if [ -n "${composerCmd:-}" ]
        then
            return # already found
        fi
        findPhpBinary
        composerCmd=''
        for checkDir in . .. ../..
        do
            if [ -f $checkDir/composer.phar ]
            then
                composerCmd="${phpBinary:-php} $checkDir/composer.phar"
                break
            fi
        done
        if [ -n "${composerCmd:-}" ]
        then
            true # is set
        elif [ -n "$(type -t composer.phar)" ] || [ -z "$(type -t composer)" ]
        then
            composerCmd=$(type -p composer.phar)
            composerCmd="${phpBinary:-php} ${composerCmd:-composer.phar}"
        else
            composerCmd=$(type -p composer)
            composerCmd="${phpBinary:-php} ${composerCmd:-composer}"
        fi

}

runCheckComposer() {
    if ! $gitListFiles --quiet -- 'composer.*'
    then
        showCommand composer validate
        findComposer
        $composerCmd validate || {
            echo
            echo 'to resolve a merge conflict, run "composer update --lock", updating the dependencies is not desired' | grep --color -e --lock
            showWarning
        }
    fi
}

runCheckPhpcs() {
    phpCs=("$(getInVendorBin phpcs)" --colors --report-width=auto -l -p)
    $whenNoMerge $gitListFiles -- '*.php' '*.js' '*.css' | $xArgs0 -- "${phpCs[@]}" || showWarning
    # config is in project dir
}

#check php files with phpstan
checkPhpStan () {
    local binStan confStan
    binStan=$(getInVendorBin phpstan)
    if [ ! -f "$binStan" ]
    then
        binStan=$(find -H ../../*/*/vendor/bin/phpstan -maxdepth 0 -type f -executable -print -quit)
        [ -f "$binStan" ] || binStan=./vendor/bin/phpstan
    fi
    [ -f .phpstan.neon ] && confStan='-c .phpstan.neon' || confStan=''
    $xArgs0 -- "$binStan" analyse $confStan
}
runCheckPhpstan() {
    $whenNoMerge $gitListFiles -- '*.php' | checkPhpStan || showWarning
}

runCheckPhpControllers() {
    if $whenNoMerge $gitListFiles --quiet -- '*Controller.php'
    then
        true # no controller
    elif ! $gitListFiles -- '*Controller.php' | grep ' public function' | grep -v 'Action(' | grep .
    then
        echo 'public functions in controllers should be routes only, named xxxAction(), above have wrong name'
        showWarning
    fi
}

runCheckShellscript() {
    $gitListFiles -- '*.sh' | $xArgs0n1 -- bash -n # syntax
    $whenNoMerge $gitListFiles -- '*.sh' | $xArgs0 -- shellcheck || showWarning # style
}

runSharedChecks() {
    runValidPhp
    runCheckTranslation
    runCheckDatabase
    runCheckTwig
    runCheckYaml
    runCheckComposer
    runCheckPhpcs
    runCheckPhpstan
    runCheckPhpControllers
    runCheckShellscript
}

setReturnValue() {
    if [ "0" != "$retVal" ]
    then
        echo failed
        return $retVal
    fi
}
