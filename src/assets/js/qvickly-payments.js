jQuery( function ( $ ) {
    if ( typeof QvicklyPaymentsParams === "undefined" ) {
        return false
    }

    const QvicklyPayments = {
        params: QvicklyPaymentsParams,
        gatewayId: QvicklyPaymentsParams.gatewayId,
        sessionId: QvicklyPaymentsParams.sessionId,
        i18n: {},

        init: () => {
            $( "body" ).on( "click", "input#place_order, button#place_order", ( e ) => {
                // Do not allow a purchase to go through if ANY error occurs.
                try {
                    if ( ! QvicklyPayments.isActiveGateway() ) {
                        return false
                    }

                    QvicklyPayments.submitOrder( e )
                } catch ( error ) {
                    QvicklyPayments.printNotice( QvicklyPayments.params.i18n.genericError )
                    console.error( error )
                    return false
                }
            } )
        },

        /**
         * Prints a notice on the checkout page.
         * @param {string} message - The message to be displayed.
         * @returns {void}
         */
        printNotice: ( message ) => {
            const elementId = `${ QvicklyPayments.gatewayId }-error-notice`

            // Remove any existing notice that we have created. This won't remove the default WooCommerce notices.
            $( `#${ elementId }` ).remove()

            const html = `<div id='${ elementId }' class='woocommerce-NoticeGroup'><ul class='woocommerce-error' role='alert'><li>${ message }</li></ul></div>`
            $( "form.checkout" ).prepend( html )

            document.getElementById( elementId ).scrollIntoView( { behavior: "smooth" } )
        },

        /**
         * Logs a message to the server.
         * @param {string} message - The message to be logged.
         * @param {string} level - The log level. Default is "notice".
         * @returns {void}
         */
        logToFile: ( message, level = "notice" ) => {
            const { logToFileUrl, logToFileNonce, reference } = QvicklyPayments.params
            console.debug( message )

            $.ajax( {
                url: logToFileUrl,
                type: "POST",
                dataType: "json",
                data: {
                    level,
                    reference,
                    message: message,
                    nonce: logToFileNonce,
                },
            } )
        },

        /**
         * Unblocks the UI.
         * @returns {void}
         */
        unblockUI: () => {
            $( ".woocommerce-checkout-review-order-table" ).unblock()
            $( "form.checkout" ).removeClass( "processing" ).unblock()
        },

        /**
         * Blocks the UI.
         * @returns {void}
         */
        blockUI: () => {
            /* Order review. */
            $( ".woocommerce-checkout-review-order-table" ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )

            $( "form.checkout" ).addClass( "processing" )
            $( "form.checkout" ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )
        },

        /**
         * Checks if the Qvickly Payments is current gateway.
         * @returns {boolean} - True if current gateway, false otherwise.
         */
        isActiveGateway: () => {
            if ( $( 'input[name="payment_method"]:checked' ).length ) {
                const currentGateway = $( 'input[name="payment_method"]:checked' ).val()
                return currentGateway.indexOf( QvicklyPayments.gatewayId ) >= 0
            }

            return false
        },

        /**
         * Update the nonce values.
         *
         * This is required when a guest user is logged in and the nonce values are updated since the nonce is associated with the user ID (0 for guests).
         *
         * @param {object} nonce An object containing the new nonce values.
         * @returns {void}
         */
        updateNonce: ( nonce ) => {
            for ( const key in nonce ) {
                if ( key in QvicklyPayments.params ) {
                    QvicklyPayments.params[ key ] = nonce[ key ]
                }
            }
        },

        /**
         * Handles failure to create WooCommerce order.
         *
         * @param {string} error - The error message.
         * @param {string} message - The message to be displayed.
         * @returns {void}
         */
        submitOrderFail: ( error, message ) => {
            console.debug( "[%s] Woo failed to create the order. Reason: %s", error, message )

            QvicklyPayments.unblockUI()
            $( document.body ).trigger( "checkout_error" )
            $( document.body ).trigger( "update_checkout" )

            // update_checkout clears notice.
            QvicklyPayments.printNotice( message )
        },

        /**
         * Submits the checkout form to WooCommerce for order creation.
         *
         * @param {Event} e - The event object.
         * @returns {void}
         */
        submitOrder: ( e ) => {
            if ( $( "form.checkout" ).is( ".processing" ) ) {
                return false
            }

            e.preventDefault()
            QvicklyPayments.blockUI()

            const { submitOrderUrl } = QvicklyPayments.params
            $.ajax( {
                type: "POST",
                url: submitOrderUrl,
                data: $( "form.checkout" ).serialize(),
                dataType: "json",
                success: async ( data ) => {
                    try {
                        if ( data.nonce ) {
                            QvicklyPayments.updateNonce( data.nonce )
                        }

                        if ( "success" === data.result ) {
                            const { order_key: orderId, customer, redirect } = data

                            QvicklyPayments.logToFile(
                                `Successfully placed order ${ orderId }. Redirecting customer to ${ redirect }.`,
                            )

                            window.location = redirect
                        } else {
                            console.warn( "AJAX request succeeded, but the Woo order was not created.", data )
                            throw "SubmitOrder failed"
                        }
                    } catch ( err ) {
                        console.error( err )
                        if ( data.messages ) {
                            // Strip HTML code from messages.
                            const messages = data.messages.replace( /<\/?[^>]+(>|$)/g, "" )

                            QvicklyPayments.logToFile( "Checkout error | " + messages, "error" )
                            QvicklyPayments.submitOrderFail( "submitOrder", messages )
                        } else {
                            QvicklyPayments.logToFile( "Checkout error | No message", "error" )
                            QvicklyPayments.submitOrderFail( "submitOrder", "Checkout error" )
                        }
                    }
                },
                error: ( data ) => {
                    try {
                        QvicklyPayments.logToFile( "AJAX error | " + JSON.stringify( data ), "error" )
                    } catch ( e ) {
                        QvicklyPayments.logToFile( "AJAX error | Failed to parse error message.", "error" )
                    }
                    QvicklyPayments.submitOrderFail( "AJAX", "Something went wrong, please try again." )
                },
            } )
        },

        /**
         * Informs Qvickly to proceed with creating the order in their system.
         *
         * @param {string} orderId The WC order ID.
         * @param {string} sessionId The Qvickly Payments session ID.
         * @returns {void}
         */
        createOrder: ( orderId, sessionId ) => {
            const { createOrderUrl, createOrderNonce } = QvicklyPayments.params

            $.ajax( {
                type: "POST",
                url: createOrderUrl,
                dataType: "json",
                data: {
                    order_key: orderId,
                    session_id: sessionId,
                    nonce: createOrderNonce,
                },
                async: false,
                success: ( data ) => {
                    if ( ! data.success ) {
                        submitOrderFail(
                            "createOrder",
                            "The order was successful created, but the payment could not be created.",
                        )
                        return
                    }

                    const {
                        data: { location },
                    } = data
                    window.location = location
                },
                error: ( jqXHR, textStatus, errorThrown ) => {
                    console.debug( "Error:", textStatus, errorThrown )
                    console.debug( "Response:", jqXHR.responseText )

                    console.error( errorThrown )
                    submitOrderFail(
                        "createOrder",
                        "The order was successful created, but the payment could not be created.",
                    )
                },
            } )
        },
    }

    QvicklyPayments.init()
} )
