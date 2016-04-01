/**
 * Created by fedotov on 29.03.16.
 */


//Some global vars
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
selected_node = "";


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
        },
        strings: ft_strings,
        activate: function (e, data) {
            node = data.node;
            selected_node = node.key;
            $("#objects").fancytree(
                "option",
                "source",
                {
                    url: "index.php",
                    data: {act: "get_objects", path: selected_node},
                    success: function () {}
                }
            )

        }


    });

    $("#objects").fancytree({
        idPrefix:"fto_",
        cookieId:"fto_",
        strings: ft_strings,
        extensions: ["persist", "table", "edit", "glyph" ],
        glyph: glyph_opts,
        activate: function (event, data) {
        },
        table: {
            indentation: 20,
            nodeColumnIdx: 1,
            checkboxColumnIdx: 0
        },
        renderColumns:function(e,data){
            var node = data.node,
                $tdList = $(node.tr).find(">td");
            $tdList.eq(2).text(node.data.dtype)
        },
        checkbox:true,
        selectMode: 2,
        source: []
    });

    //collapsing
    $("#navbar_tree_btn").click(function () {
        $("#main-row .collapse").collapse('toggle');
        $("#navbar_tree_btn").toggleClass('active');

    });
    
});






