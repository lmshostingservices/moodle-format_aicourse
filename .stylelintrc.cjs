// Retain every Moodle lint rule, extending its older CSS grammar for the
// standards-based container queries, system colours and maths used here.
const fs = require('node:fs');
const path = require('node:path');

let root = process.env.MOODLE_ROOT || __dirname;
while (!fs.existsSync(path.join(root, '.stylelintrc'))) {
    const parent = path.dirname(root);
    if (parent === root) {
        throw new Error('Run the stylesheet checks inside a Moodle source tree, or set MOODLE_ROOT.');
    }
    root = parent;
}

module.exports = {
    extends: path.join(root, '.stylelintrc'),
    rules: {
        'csstree/validator': {
            types: {
                'length-percentage': '| <acf-math()>',
                'acf-math()': 'calc( <acf-sum> ) | min( <acf-sum># ) | max( <acf-sum># ) | ' +
                    'clamp( <acf-sum>, <acf-sum>, <acf-sum> )',
                'acf-sum': "<acf-product> [ [ '+' | '-' ] <acf-product> ]*",
                'acf-product': "<acf-value> [ [ '*' | '/' ] <acf-value> ]*",
                'acf-value': '<number> | <length> | <percentage> | <acf-math()> | ( <acf-sum> )',
                color: '| Canvas | CanvasText | LinkText | ButtonText | ButtonFace | ButtonBorder | GrayText | ' +
                    'Highlight | HighlightText | Mark | MarkText | Field | FieldText | ' +
                    'AccentColor | AccentColorText | SelectedItem | SelectedItemText',
            },
            properties: {
                'font-size': '| <acf-math()>',
                'inline-size': '| <acf-math()>',
                'min-inline-size': '| <acf-math()>',
                'padding-inline': '| [ <length-percentage> | <acf-math()> ]{1,2}',
                padding: '| [ <length-percentage> | <acf-math()> ]{1,4}',
                'container-type': 'normal | size | inline-size',
                'container-name': 'none | <custom-ident>+',
                'text-wrap': 'wrap | nowrap | balance | pretty | stable',
            },
            atrules: {
                container: {
                    prelude: '<custom-ident>? ( [ min-width | max-width | width ] : <length> )',
                },
            },
        },
    },
};
