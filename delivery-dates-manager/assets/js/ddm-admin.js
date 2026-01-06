(function($) {
    'use strict';

    var DDMAdmin = {
        init: function() {
            this.bindEvents();
            this.initAccordion();
        },

        bindEvents: function() {
            $(document).on('click', '.ddm-zone-header', this.togglePanel);
            $(document).on('change', '.ddm-zone-content input[type="checkbox"]:first', this.updateZoneStatus);
        },

        initAccordion: function() {
            $('.ddm-zone-panel').each(function() {
                var $panel = $(this);
                var isEnabled = $panel.find('input[name*="[enabled]"]').is(':checked');
                
                if (isEnabled) {
                    $panel.addClass('ddm-zone-enabled');
                }
            });
        },

        togglePanel: function(e) {
            var $header = $(this);
            var $panel = $header.closest('.ddm-zone-panel');
            var $content = $panel.find('.ddm-zone-content');
            
            $panel.toggleClass('active');
            $content.slideToggle(200);
            
            $('.ddm-zone-panel').not($panel).removeClass('active').find('.ddm-zone-content').slideUp(200);
        },

        updateZoneStatus: function() {
            var $checkbox = $(this);
            var $panel = $checkbox.closest('.ddm-zone-panel');
            var $status = $panel.find('.ddm-zone-status');
            
            if ($checkbox.is(':checked')) {
                $status.removeClass('inactive').addClass('active').text('Active');
                $panel.addClass('ddm-zone-enabled');
            } else {
                $status.removeClass('active').addClass('inactive').text('Inactive');
                $panel.removeClass('ddm-zone-enabled');
            }
        }
    };

    $(document).ready(function() {
        DDMAdmin.init();
    });

})(jQuery);
