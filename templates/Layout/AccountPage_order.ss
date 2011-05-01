<div class="typography">
	
	<% if Order %>
    <% control Order %>
      <h2>Order #$ID</h2>
      <h3>$Created.Nice</h3>
      
      <div id="OrderInformation">

			  <table id="ShippingTable" class="infotable">
			    <tr>
			      <th>To</th>
			    </tr>
			    <tr>
			      <td>
			        <% control Member %>
			          $FirstName $Surname<br />
			          $Address<br />
			          $AddressLine2<br />
			          $City<br />
			          $PostalCode<br />
			          $State<br />
			          $Country<br />
			        <% end_control %>
			      </td>
			    </tr>
			  </table>
      
        <% include Order %>
          
			  <% if Payments %>
			    <table id="PaymentTable" class="infotable">
			      <thead>     
			        <tr class="gap mainHeader">
			            <th colspan="10" class="left">Payment(s)</th>
			        </tr>
			        <tr>
			          <th scope="row" class="twoColHeader">Date</th>
			          <th scope="row"  class="twoColHeader">Amount</th>
			          <th scope="row"  class="twoColHeader">Payment Status</th>
			          <th scope="row" class="twoColHeader">Method</th>
			          <th scope="row" class="twoColHeader">Note</th>
			        </tr>
			        <% control Payments %>  
			          <tr>
			            <td class="price">$LastEdited.Nice24</td>
			            <td class="price">$Amount.Nice $Currency</td>
			            <td class="price">$Status</td>
			            <td class="price">$PaymentMethod</td>
			            <td class="price">$Message.NoHTML</td>
			          </tr>
			        <% end_control %>
			      </tbody>
			    </table>
			    
			    <table id="OutstandingTable" class="infotable">
			      <tbody>
			        <tr class="gap summary" id="Outstanding">
			          <th colspan="3" scope="row" class="threeColHeader"><strong>Total outstanding</strong></th>
			          <td class="right"><strong>$TotalOutstanding.Nice </strong></td>
			        </tr>
			      </tbody>
			    </table>
			  <% end_if %>
			  
			  <% if Downloads %>
			    <table id="PaymentTable" class="infotable">
            <thead>     
              <tr class="gap mainHeader">
                  <th colspan="10" class="left">Download(s)</th>
              </tr>
              <tr>
                <th scope="row" class="twoColHeader">Product</th>
                <th scope="row"  class="twoColHeader">Quantity</th>
                <th scope="row"  class="twoColHeader">Download Count</th>
                <th scope="row"  class="twoColHeader">Download Link</th>
              </tr>
              <% control Downloads %>  
                <tr>
                  <% control Object %> 
                  <td class="price">$Title</td>
                  <% end_control %>
                  
                  <td class="price">$Quantity</td>
                  <td class="price">$DownloadCount</td>
                  <td class="price"><a href="$DownloadLink">Download</a></td>
                </tr>
              <% end_control %>
            </tbody>
          </table>
			  <% end_if %>
			  
			</div>
      
    <% end_control %>
  <% else %>
    <div id="AccountMessage">$Message.Raw</div>
  <% end_if %>

</div>