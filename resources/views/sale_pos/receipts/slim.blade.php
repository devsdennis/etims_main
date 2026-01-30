<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <style>
            body {
                font-family: Cambria, serif;
                font-size: 12px;
                margin:1px;
                margin-left: 0.17in;
                margin-right: 0.17in;
            }
            .ticket { width: auto; padding: 10px; border: 1px solid #ccc; max-width: 100%; }
            .centered { text-align: center }
            .text-box { margin-bottom: -1px; margin-top: -1px; }
            .border-top { border-top: 2px solid #000;  margin-top: 0px;  
            padding-top: 1px;   }
            .textbox-info { display: flex; justify-content: space-between; margin-bottom: 5px }
            .f-left { width: 40% }
            .f-right { width: 60%; text-align: right }
            .width-50 { width: 50% }
            .flex-box { display: flex; justify-content: space-between }
            .left { width: 40% }
            .sub-headings { font-weight: bold }
            table { width: 100%; border-collapse: collapse; font-weight: bold }
            th, td { border: 2px solid #000; padding: 8px; text-align: left }
            .text-right { text-align: right }
            .description { width: 40% }
            .quantity { width: 10% }
            .unit_price { width: 10% }
            .price { width: 15% }
            .border-bottom-dotted { border-bottom: 1px dotted #ccc }
            .border-bottom { border-bottom: 1px solid #ccc }
            .bw { border: 1px solid #ccc; padding: 5px }
            .f-8 { font-size: 8px }
            .mb-10 { margin-bottom: 10px }
            .mt-5 { margin-top: 5px }
            .center-block { display: block; margin-left: auto; margin-right: auto }
            /*@media print {*/
            /*    * {*/
            /*        font-size: 12px;*/
            /*        font-family: Cambria, serif;*/
            /*        word-break: break-all;*/
            /*    }*/

            /*    .f-8 {*/
            /*        font-size: 8px !important;*/
            /*    }*/

            /*    .headings {*/
            /*        font-size: 16px;*/
            /*        font-weight: 700;*/
            /*        text-transform: uppercase;*/
            /*        white-space: nowrap;*/
            /*    }*/

            /*    .sub-headings {*/
            /*        font-size: 15px !important;*/
            /*        font-weight: 700 !important;*/
            /*    }*/

            /*    .border-top {*/
            /*        border-top: 1px solid #242424;*/
            /*    }*/

            /*    .border-bottom {*/
            /*        border-bottom: 1px solid #242424;*/
            /*    }*/

            /*    .border-bottom-dotted {*/
            /*        border-bottom: 1px dotted darkgray;*/
            /*    }*/

            /*    td.serial_number,*/
            /*    th.serial_number {*/
            /*        width: 5%;*/
            /*        max-width: 5%;*/
            /*    }*/

            /*    td.description,*/
            /*    th.description {*/
            /*        width: 35%;*/
            /*        max-width: 35%;*/
            /*    }*/

            /*    td.quantity,*/
            /*    th.quantity {*/
            /*        width: 15%;*/
            /*        max-width: 15%;*/
            /*        word-break: break-all;*/
            /*    }*/

            /*    td.unit_price,*/
            /*    th.unit_price {*/
            /*        width: 25%;*/
            /*        max-width: 25%;*/
            /*        word-break: break-all;*/
            /*    }*/

            /*    td.price,*/
            /*    th.price {*/
            /*        width: 20%;*/
            /*        max-width: 20%;*/
            /*        word-break: break-all;*/
            /*    }*/

            /*    .centered {*/
            /*        text-align: center;*/
            /*        align-content: center;*/
            /*    }*/

            /*    .ticket {*/
            /*        width: 100%;*/
            /*        max-width: 100%;*/
            /*    }*/

            /*    img {*/
            /*        max-width: inherit;*/
            /*        width: auto;*/
            /*    }*/

            /*    .hidden-print,*/
            /*    .hidden-print * {*/
            /*        display: none !important;*/
            /*    }*/
            /*}*/
        </style>
        <title>Receipt-{{ $receipt_details->invoice_no }}</title>
    </head>
    <body>
        <div class="ticket">
            <div class="text-box" style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; text-align: center;">
            @if(!empty($receipt_details->logo))
                <div style="margin-right: 15px;">
                    <img src="{{ $receipt_details->logo }}" class="img" style="max-height: 80px;">
                </div>
            @endif
                <div>
                    @if(!empty($receipt_details->header_text))
                        <span class="headings">{!! $receipt_details->header_text !!}</span>
                    @endif<br>
                    @if(!empty($receipt_details->display_name))
                        <span class="headings">{{ $receipt_details->display_name }}</span>
                    @endif<br
                    @if(!empty($receipt_details->address))
                        {!! $receipt_details->address !!}
                    @endif
                    @if(!empty($receipt_details->contact))
                        {!! $receipt_details->contact !!}
                    @endif
                    @if(!empty($receipt_details->contact) && !empty($receipt_details->website))
                    @endif
                    @if(!empty($receipt_details->website))
                        {{ $receipt_details->website }}
                    @endif
                    @if(!empty($receipt_details->location_custom_fields))
                        {{ $receipt_details->location_custom_fields }}
                    @endif
                    @if (!empty($receipt_details->sub_heading_line1))
                    {{ $receipt_details->sub_heading_line1 }}<br>
                    @endif
                    @if (!empty($receipt_details->sub_heading_line2))
                        {{ $receipt_details->sub_heading_line2 }}<br>
                    @endif
                    @if (!empty($receipt_details->sub_heading_line3))
                        {{ $receipt_details->sub_heading_line3 }}<br>
                    @endif
                    @if (!empty($receipt_details->sub_heading_line4))
                        {{ $receipt_details->sub_heading_line4 }}<br>
                    @endif
                    @if (!empty($receipt_details->sub_heading_line5))
                        {{ $receipt_details->sub_heading_line5 }}<br>
                    @endif
                    @if(!empty($receipt_details->tax_info1))
                        <b>{{ $receipt_details->tax_label1 }}</b> {{ $receipt_details->tax_info1 }}<br>
                    @endif
                    @if(!empty($receipt_details->tax_info2))
                        <b>{{ $receipt_details->tax_label2 }}</b> {{ $receipt_details->tax_info2 }}
                    @endif<br>
                    <!--@if(!empty($receipt_details->invoice_heading))-->
                    <!--    <span class="sub-headings">{!! $receipt_details->invoice_heading !!}</span>-->
                    <!--@endif-->
                    <!--@if(!empty($receipt_details->payment_status))-->
                    <!--   <br><b class="sub-headings" style="font-size: 16px;">{{ $receipt_details->payment_status == 'due' ? 'INVOICE' : 'RECEIPT' }}</b>-->
                    <!--@endif-->
                   @if(!empty($receipt_details->payment_status))
					<span class="sub-headings centered">
						@if(isset($receipt_details->is_quotation) && $receipt_details->is_quotation == 't')
							QUOTATION
						@elseif($receipt_details->payment_status == 'due')
							INVOICE
						@elseif($receipt_details->payment_status == 'paid')
							RECEIPT
						@else
							{!! $receipt_details->invoice_heading ?? '' !!}
						@endif
					</span>
					@elseif(!empty($receipt_details->invoice_heading))
						<span class="sub-headings">{!! $receipt_details->invoice_heading !!}</span>
					@endif
                </div> 
               
            </div>

            <div class="border-top textbox">
                <p>
                    <strong><span class="f-left">{!! $receipt_details->invoice_no_prefix !!}</span></strong> <span class="f-right">{{ $receipt_details->invoice_no }}</span><br>
                    <strong><span class="f-left">{!! $receipt_details->date_label !!}</span></strong> <span class="f-right">{{ $receipt_details->invoice_date }}</span><br>
                    @if(!empty($receipt_details->due_date_label))
                        <strong><span class="f-left">{{ $receipt_details->due_date_label }}</span></strong> <span class="f-right">{{ $receipt_details->due_date ?? '' }}</span><br>
                    @endif
                    @if(!empty($receipt_details->customer_info))
{{--                        <strong><span class="f-left">{{ $receipt_details->customer_label ?? 'Customer' }}</span></strong> <span class="f-right">{!! $receipt_details->customer_info !!}</span><br>--}}
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong>{{ $receipt_details->customer_label ?? 'Customer' }}</strong>
                        <span>{!! $receipt_details->customer_info !!} {!! $receipt_details->customer_mobile !!}</span>
                    </div>
                    @endif
                    @if(!empty($receipt_details->client_id_label))
                        <strong><span class="f-left">{{ $receipt_details->client_id_label }}</span></strong> <span class="f-right">{{ $receipt_details->client_id }}</span><br>
                    @endif
                
                
                    @if (!empty($receipt_details->customer_tax_label))
                    <span class="f-left"><strong> {{ $receipt_details->customer_tax_label }}</strong></span>
                    <span class="f-right">{{ $receipt_details->customer_tax_number }}</span><br>
                    @endif
                    @if (!empty($receipt_details->customer_custom_fields))
                    <span class="centered">{!! $receipt_details->customer_custom_fields !!}</span><br>
                   @endif
                
                
                    @if(!empty($receipt_details->sales_person_label))
                        <strong><span class="f-left">{{ $receipt_details->sales_person_label }}</span></strong> <span class="f-right">{{ $receipt_details->sales_person }}</span>
                    @endif
                </p>
            </div>

            <table class="border-top">
                <thead>
                    <tr>
                        <th class="description centered">{{ $receipt_details->table_product_label }}</th>
                        <th class="quantity centered">{{ $receipt_details->table_qty_label }}</th>
                        @if(empty($receipt_details->hide_price))
                            <th class="unit_price centered">{{ $receipt_details->table_unit_price_label }}</th>
                            <th class="price centered">{{ $receipt_details->table_subtotal_label }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipt_details->lines as $line)
                        <tr>
                            <td class="description">
                                {{ $line['name'] }}
                                @if(!empty($line['sub_sku'])), {{ $line['sub_sku'] }}@endif 
                                @if(!empty($line['brand'])), {{ $line['brand'] }}@endif 
                                @if(!empty($line['sell_line_note']))
                                    <br><small>{{ $line['sell_line_note'] }}</small>
                                @endif
                            </td>
                            <td class="quantity text-right">{{ $line['quantity'] }} <small>{{ $line['units'] }}</small></td>
                            @if(empty($receipt_details->hide_price))
                                <td class="unit_price text-right">{{ $line['unit_price_inc_tax'] }}</td>
                                <td class="price text-right">{{ $line['line_total'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(empty($receipt_details->hide_price))
                <div class="border-top textbox">
                    <p>
                        <strong><span class="f-left">{!! $receipt_details->subtotal_label !!}</span></strong> <span class="f-right">{{ $receipt_details->subtotal }}</span><br>

                        @if(!empty($receipt_details->discount))
                            <span class="f-left">
                                {!! $receipt_details->discount_label !!}
                                @if(!empty($receipt_details->discount_type))({{ $receipt_details->discount_type }})@endif
                            </span> 
                            <span class="f-right">(-) {{ $receipt_details->discount }}</span><br>
                        @endif

                        @if(!empty($receipt_details->tax))
                            <span class="f-left">{!! $receipt_details->tax_label !!}</span> 
                            <span class="f-right">(+) {{ $receipt_details->tax }}</span><br>
                        @endif

                        <strong><span class="f-left">{!! $receipt_details->total_label !!}</span></strong> 
                        <span class="f-right"><strong>{{ $receipt_details->total }}</strong></span><br>

                        @if(!empty($receipt_details->payments))
                            @foreach($receipt_details->payments as $payment)
                                <span class="f-left">{{ $payment['method'] }} ({{ $payment['date'] }})</span> 
                                <span class="f-right">{{ $payment['amount'] }}</span><br>
                            @endforeach
                        @endif

                        <!--@if(!empty($receipt_details->total_paid))-->
                        <!--    <span class="f-left">{!! $receipt_details->total_paid_label !!}</span> -->
                        <!--    <span class="f-right">{{ $receipt_details->total_paid }}</span><br>-->
                        <!--@endif-->

                        @if(!empty($receipt_details->shipping_charges))
                            <span class="f-left">{!! $receipt_details->shipping_charges_label !!}</span>
                            <span class="f-right">{{ $receipt_details->shipping_charges }}</span>
                        @endif
                        @if(!empty($receipt_details->packing_charge))
                            <span class="f-left">{!! $receipt_details->packing_charge_label !!}</span>
                            <span class="f-right">{{ $receipt_details->packing_charge }}</span>
                        @endif
                        @if(!empty($receipt_details->total_due))
                            <span class="f-left">{!! $receipt_details->total_due_label !!}</span> 
                            <span class="f-right">{{ $receipt_details->total_due }}</span>
                        @endif
                        @if(!empty($receipt_details->shipping_details))
                            <span class="f-left">{!! $receipt_details->shipping_details_label !!}</span>
                            <span class="f-right">{{ $receipt_details->shipping_details }}</span>
                        @endif
                    </p>
                </div>
            @endif

            @if(!empty($receipt_details->additional_notes))
                <p class="centered border-top">{!! nl2br($receipt_details->additional_notes) !!}</p>
            @endif

            @if(!empty($receipt_details->footer_text))
                <p class="centered">{!! $receipt_details->footer_text !!}</p>
            @endif

            @if(!empty($receipt_details->etims_url))
                <div class="border-top mb-10">
                        <p class="centered">
                          <strong><span style="font-size: 18px;">CONTROL UNIT INFO</span></strong>  <br>
                            <strong>Serial No:</strong> {{ $receipt_details->serial_number }}<br>
                            <strong>Invoice No:</strong> {{ $receipt_details->etims_invoice_number }}<br>
                            <strong>Customer Pin:</strong> {{ $receipt_details->customer_pin }}<br>
                            <strong>Receipt Ref:</strong> {{ $receipt_details->etims_receipt }}<br>
                            <strong>Date:</strong> {{ $receipt_details->etims_date }} {{ $receipt_details->etims_time }}
                        </p>
						 @if(!empty($receipt_details->etims_url))
							<div style="display: flex; align-items: center; padding-bottom: 2px;">
							<img class="center-block" style="max-width: 150px; margin-bottom: 2px;" src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->etims_url, 'QRCODE', 3, 3) }}">
							</div>   
							@elseif($receipt_details->show_qr_code && !empty($receipt_details->qr_code_text))
							<div style="display: flex; align-items: center;padding-bottom: 2px;">
								<img 
									src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE') }}" 
									style="float: right; width: 80px; height: 80px;margin-top:10px;" 
									alt="QR Code"
								> 
							</div>    
            			@endif
                 
                    <!--@if(!empty($receipt_details->etims_url))-->
                    <!--<div class="centered mt-5">-->
                    <!--    <img class="center-block" style="max-width: 150px;" src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->etims_url, 'QRCODE', 3, 3) }}">-->
                    <!--</div>-->
                    <!--@endif-->
                </div>
            <!--@elseif($receipt_details->show_qr_code && !empty($receipt_details->qr_code_text))-->
            <!--    <img -->
            <!--        src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE') }}" -->
            <!--        style="float: right; width: 80px; height: 80px;margin-top:10px;" -->
            <!--        alt="QR Code"-->
            <!--    > -->
            @endif
        </div>
    </body>
</html>