<h3>Order #$ID - $Status</h3>
<p class="OrderMeta">
  $OrderedOn.Format(j M Y - g:i a)<br />
  ($PaymentStatus)
</p>

<div id="OrderInformation">

  <% include OrderMember %>

  <% include Order %>
    
  <% if Payments %>
    <% include OrderPayments %>
  <% end_if %>
  
  <% if Downloads %>
    <% include OrderDownloads %>
  <% end_if %>
  
</div>
