/**
 * Created by fedotov on 29.03.16.
 */


$(function() {
    //create folders tree
    $("#tree").fancytree({
        extensions : ["glyph", "persist", "wide"],
        glyph: glyph_opts,
        generateIds: true,
        idPrefix: "ftt_",
        cookieId: "ftt_",
        source : {
            url: "index.php",
            data: {
                act: "get_folders"
            }
        },
        lazyLoad: function (event, data) {
            data.result = {
                url: "index.php",
                data: {
                    act: "get_folders",
                    path: data.node.key
                }
            }
        },
        toggleEffect: {
            effect: "drop",
            options: {
                direction: "left"
            },
            duration: 200
        },
        wide: {
            iconWidth: "1em",
            iconSpacing: "0.5em",
            levelOfs: "1.5em"
        },
        persist: {
            expandLazy: true
        }


    })
    
    
});

glyph_opts = {
    map: {
        doc: "glyphicon glyphicon-file",
        docOpen: "glyphicon glyphicon-file",
        checkbox: "glyphicon glyphicon-unchecked",
        checkboxSelected: "glyphicon glyphicon-check",
        checkboxUnknown: "glyphicon glyphicon-share",
        dragHelper: "glyphicon glyphicon-play",
        dropMarker: "glyphicon glyphicon-arrow-right",
        error: "glyphicon glyphicon-warning-sign",
        expanderClosed: "glyphicon glyphicon-menu-right",
        expanderLazy: "glyphicon glyphicon-menu-right",  // glyphicon-plus-sign
        expanderOpen: "glyphicon glyphicon-menu-down",  // glyphicon-collapse-down
        folder: "glyphicon glyphicon-folder-close",
        folderOpen: "glyphicon glyphicon-folder-open",
        loading: "glyphicon glyphicon-refresh glyphicon-spin"
    }
};

