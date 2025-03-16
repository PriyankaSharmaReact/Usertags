jQuery(document).ready(function ($) {
            $('#user_tags_select, #user_tag_filter').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { action: 'ut_search_user_tags', term: params.term };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });
        });