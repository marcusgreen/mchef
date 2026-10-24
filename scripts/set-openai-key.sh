# Adds OPENAI_KEY to the current shell environment for recipes using "${OPENAI_KEY}" or "${OPENAI_KEY:-}".
#
# Must be sourced, not executed, so the variable reaches your shell:
#
#   source scripts/set-openai-key.sh           # use saved key if present, otherwise prompt
#   source scripts/set-openai-key.sh --save    # prompt and save the key for future sessions
#   source scripts/set-openai-key.sh --new     # prompt even if a key is saved
#   source scripts/set-openai-key.sh --clear   # unset the variable and delete the saved key
#
# The key is read without echoing, so it never appears on screen or in shell history.
# Saved keys live in ~/.config/mchef/openai_key (mode 600).

# Detect being executed rather than sourced (bash and zsh).
if [ -n "${BASH_VERSION:-}" ] && [ "${BASH_SOURCE[0]}" = "$0" ]; then
    echo "Run this with 'source $0' - executing it cannot change your shell's environment." >&2
    exit 1
fi
if [ -n "${ZSH_VERSION:-}" ] && [[ "${ZSH_EVAL_CONTEXT:-}" != *:file* ]]; then
    echo "Run this with 'source $0' - executing it cannot change your shell's environment." >&2
    exit 1
fi

_mchef_key_file="$HOME/.config/mchef/openai_key"
_mchef_key_mode="load"

case "${1:-}" in
    "") ;;
    --save) _mchef_key_mode="save" ;;
    --new) _mchef_key_mode="new" ;;
    --clear) _mchef_key_mode="clear" ;;
    -h|--help)
        sed -n '3,10p' "${BASH_SOURCE[0]:-${(%):-%x}}" | sed 's/^# \{0,1\}//'
        unset _mchef_key_file _mchef_key_mode
        return 0
        ;;
    *)
        echo "Unknown option: $1 (use --save, --new, --clear or --help)" >&2
        unset _mchef_key_file _mchef_key_mode
        return 1
        ;;
esac

if [ "$_mchef_key_mode" = "clear" ]; then
    unset OPENAI_KEY
    rm -f "$_mchef_key_file"
    echo "OPENAI_KEY unset and saved key removed."
    unset _mchef_key_file _mchef_key_mode
    return 0
fi

if [ "$_mchef_key_mode" = "load" ] && [ -r "$_mchef_key_file" ]; then
    OPENAI_KEY="$(cat "$_mchef_key_file")"
    export OPENAI_KEY
    echo "OPENAI_KEY loaded from $_mchef_key_file."
    unset _mchef_key_file _mchef_key_mode
    return 0
fi

printf 'Paste your OpenAI API key (input hidden): '
IFS= read -r -s _mchef_key_input
echo

if [ -z "$_mchef_key_input" ]; then
    echo "No key entered - OPENAI_KEY not changed." >&2
    unset _mchef_key_file _mchef_key_mode _mchef_key_input
    return 1
fi

case "$_mchef_key_input" in
    sk-*) ;;
    *) echo "Warning: key does not start with 'sk-' - check it is an OpenAI API key." >&2 ;;
esac

OPENAI_KEY="$_mchef_key_input"
export OPENAI_KEY
echo "OPENAI_KEY set for this shell session."

if [ "$_mchef_key_mode" = "save" ]; then
    mkdir -p "$(dirname "$_mchef_key_file")"
    (umask 077 && printf '%s' "$OPENAI_KEY" > "$_mchef_key_file")
    chmod 600 "$_mchef_key_file"
    echo "Key saved to $_mchef_key_file - future 'source' calls will load it."
fi

unset _mchef_key_file _mchef_key_mode _mchef_key_input
