/**
 * compose-base.js - Provides basic compose javascript functions shared
 * between standarad and dynamic displays.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

var ImpComposeBase = {

    // Vars defaulting to null: editor_on, identities

    getSpellChecker: function()
    {
        return (HordeImple.SpellChecker && HordeImple.SpellChecker.spellcheck)
            ? HordeImple.SpellChecker.spellcheck
            : null;
    },

    setCursorPosition: function(input, type)
    {
        var pos, range;

        if (!(input = $(input))) {
            return;
        }

        switch (type) {
        case 'top':
            pos = 0;
            input.setValue('\n' + $F(input));
            break;

        case 'bottom':
            pos = $F(input).length;
            break;

        default:
            return;
        }

        if (input.setSelectionRange) {
            /* This works in Mozilla. */
            Field.focus(input);
            input.setSelectionRange(pos, pos);
            if (pos) {
                (function() { input.scrollTop = input.scrollHeight - input.offsetHeight; }).defer();
            }
        } else if (input.createTextRange) {
            /* This works in IE */
            range = input.createTextRange();
            range.collapse(true);
            range.moveStart('character', pos);
            range.moveEnd('character', 0);
            Field.select(range);
            range.scrollIntoView(true);
        }
    },

    updateAddressField: function(elt, address)
    {
        var v = $F(elt).strip(),
            pos = v.lastIndexOf(',');

        if (v.empty()) {
            v = '';
        } else {
            if (pos != -1) {
                v = v.substring(0, pos);
            }
            v += ', ';
        }

        elt.setValue(v + address + ', ');
    },

    onDomLoad: function()
    {
        if (CKEDITOR) {
            CKEDITOR.on('instanceReady', function(e) {
                e.editor.on('paste', function(e) {
                    if (e.data.html) {
                        e.data.html = e.data.html.stripTags();
                    }
                }.bind(this));
            }.bind(this));
        }
    }

};

document.observe('dom:loaded', ImpComposeBase.onDomLoad.bind(ImpComposeBase));
