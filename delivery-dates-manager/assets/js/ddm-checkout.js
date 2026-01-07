(function($) {
    'use strict';

    var DDMCheckout = {
        settings: {},
        selectedZone: null,
        availableDates: [],

        init: function() {
            if (typeof ddm_checkout === 'undefined') {
                return;
            }

            this.settings = ddm_checkout.zone_settings || {};
            this.bindEvents();
            this.initDatepicker();
        },

        bindEvents: function() {
            $(document).on('change', '#ddm_delivery_zone', this.onZoneChange.bind(this));
            $(document.body).on('updated_checkout', this.onCheckoutUpdate.bind(this));
        },

        initDatepicker: function() {
            var self = this;

            $('#ddm_delivery_date').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                maxDate: '+60d',
                beforeShowDay: function(date) {
                    return self.isDateAvailable(date);
                },
                onSelect: function(dateText) {
                    self.onDateSelect(dateText);
                }
            });
        },

        onZoneChange: function(e) {
            var zoneId = $(e.target).val();
            var self = this;

            this.selectedZone = zoneId;
            $('#ddm_delivery_date').val('');
            this.availableDates = [];

            if (!zoneId) {
                $('#ddm_delivery_date').prop('disabled', true);
                return;
            }

            $('#ddm_delivery_date_field').addClass('ddm-loading');

            $.ajax({
                url: ddm_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'ddm_get_zone_dates',
                    zone_id: zoneId,
                    nonce: ddm_checkout.nonce
                },
                success: function(response) {
                    $('#ddm_delivery_date_field').removeClass('ddm-loading');

                    if (response.success) {
                        self.availableDates = response.data.dates;
                        $('#ddm_delivery_date').prop('disabled', false);
                        $('#ddm_delivery_date').datepicker('refresh');

                        self.updateShipping(zoneId);
                        self.showDeliveryFee(response.data.flat_fee);

                        if (response.data.same_day_available) {
                            self.showSameDayBadge();
                        } else {
                            self.hideSameDayBadge();
                        }
                    }
                },
                error: function() {
                    $('#ddm_delivery_date_field').removeClass('ddm-loading');
                }
            });
        },

        isDateAvailable: function(date) {
            var dateString = $.datepicker.formatDate('yy-mm-dd', date);
            var dayOfWeek = date.getDay();
            var zoneSettings = this.settings[this.selectedZone];

            if (!zoneSettings) {
                return [false, 'ddm-date-unavailable', ''];
            }

            for (var i = 0; i < this.availableDates.length; i++) {
                if (this.availableDates[i].date === dateString) {
                    var cssClass = this.availableDates[i].type === 'same_day' ? 'ddm-date-sameday' : 'ddm-date-available';
                    return [true, cssClass, this.availableDates[i].label];
                }
            }

            return [false, 'ddm-date-unavailable', ''];
        },

        onDateSelect: function(dateText) {
            var self = this;
            var selectedDate = null;

            for (var i = 0; i < this.availableDates.length; i++) {
                if (this.availableDates[i].date === dateText) {
                    selectedDate = this.availableDates[i];
                    break;
                }
            }

            if (selectedDate) {
                $('#ddm_delivery_type').val(selectedDate.type);
                
                if (selectedDate.type === 'same_day') {
                    this.showSameDayBadge();
                }
            }

            $(document.body).trigger('update_checkout');
        },

        updateShipping: function(zoneId) {
            $.ajax({
                url: ddm_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'ddm_update_shipping',
                    zone_id: zoneId,
                    nonce: ddm_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $(document.body).trigger('update_checkout');
                    }
                }
            });
        },

        showDeliveryFee: function(fee) {
            var $notice = $('.ddm-delivery-fee-notice');
            
            if (fee > 0) {
                var formattedFee = this.formatPrice(fee);
                
                if ($notice.length) {
                    $notice.html('<strong>Delivery Fee:</strong> ' + formattedFee);
                } else {
                    $('<div class="ddm-delivery-fee-notice"><strong>Delivery Fee:</strong> ' + formattedFee + '</div>')
                        .insertAfter('#ddm_delivery_date_field');
                }
            } else {
                $notice.remove();
            }
        },

        formatPrice: function(price) {
            if (typeof wc_cart_params !== 'undefined' && wc_cart_params.currency_format_symbol) {
                return wc_cart_params.currency_format_symbol + parseFloat(price).toFixed(2);
            }
            return '$' + parseFloat(price).toFixed(2);
        },

        showSameDayBadge: function() {
            if (!$('.ddm-same-day-badge').length) {
                $('<span class="ddm-same-day-badge">Same-Day Available</span>')
                    .appendTo('#ddm_delivery_zone_field label');
            }
        },

        hideSameDayBadge: function() {
            $('.ddm-same-day-badge').remove();
        },

        onCheckoutUpdate: function() {
            if (this.selectedZone) {
                $('#ddm_delivery_date').datepicker('refresh');
            }
        }
    };

    $(document).ready(function() {
        DDMCheckout.init();
    });

})(jQuery);
