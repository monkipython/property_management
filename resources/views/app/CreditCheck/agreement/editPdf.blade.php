      <h2 style="text-align:center;"><b>Tenant Rental Agreement</b></h2>
      <p>This Rental Agreement and/or Lease (agreement) shall evidence the complete terms and conditions under which the parties whose signatures appear below have agreed {!! $data['agreement']['management_name_1'] !!}. Landlord, Landlord/Lessor/Agent shall be referred to as "LANDLORD" and Tenants(s) Lessee(s) shall be referred to as "TENANT":</p>
      <table>
        <tr id="section_1">
            <td width="15"></td>
            <td width="20"><p><b><u>&nbsp;1.</u></b></p></td>
            <td width="500"></td>
        </tr>
      </table>
      <table>
          {!! $data['occupantInfo'] !!}
          <tr>
              <td></td>
              <td></td>
              <td><p>(Collectively, "Tenant") entered into this Tenant Rental Agreement ("Agreement").</p></td>
          </tr>
          <tr>
              <td width="15"></td>
              <td width="30">1.1</td>
              <td width="480">In addition to Tenant, the following individuals will also reside ath the Premises ("Occupants")</td>
          </tr>
         {!! $data['occupantNames'] !!}
          
          <tr>
              <td></td>
              <td>1.2.</td>
              <td><p>Tenant represents, warrants and covenants that all persons who will reside at the Premises as Occupants are listed in Section 1.2, above. All Occupants over the age of 18 are required to be listed in Section 1.1 as Tenant and are subject to any and all application approval requirements.</p></td>
          </tr>
          <tr>
              <td></td>
              <td>1.3.</td>
              <td><p>As consideration for this Agreement, LANDLORD agrees to rent/lease to TENANT and TENANT agree to rent/lease from LANDLORD for use SOLELY AS A PRIVATE RESIDENCE, the premises known as Section 2.</p></td>
          </tr>
          <tr>
              <td><p><b></b></p></td>
              <td width="100"><p><b><u>2. PREMISES</u></b></p></td>
          </tr>
      </table>
      <table>  
          <tr>
              <td width="15"></td>
              <td width="30">2.1.</td>
              <td width="480"><p>LANDLORD rents to TENANT and TENANT rents from LANDLORD, the real property and improvements described as Property No. {!! $data['agreement']['prop_no_1'] !!} Unit No. {!! $data['agreement']['unit_no_1'] !!}, located at   ("Premises")<br>{!! $data['agreement']['premises_1'] !!}.<br>(Address, City, State, Zip)</p></td>
          </tr>
          <tr>
              <td></td>
              <td>2.2.</td>
              <td><p>The Premises are for the sole use as a private residence by the above named Tenant(s) and Occupant(s), for a total of {!! $data['agreement']['num_occupants_1'] !!} occupants, and by no other persons without prior written consent of the Landlord.</p></td>
          </tr>
          <tr>
              <td></td>
              <td>2.3.</td>
              <td><p>The following personal property is included: {!! $data['agreement']['included_properties_1'] !!}</p></td>
          </tr>
          <tr>
              <td><p><b></b></p></td>
              <td width="300"><p><b><u>3. RENTAL TERM</u></b></p></td>
          </tr>
      </table>
        <table>
            <tr>
              <td width="15"></td>
              <td width="30">3.1</td>
              <td width="480"><p>The Rental Term begins on {!! $data['agreement']['rental_begin_date_1'] !!} ("Commencement Date"), and shall continue as a month to month tenancy until either party shall terminate this agreement; such month to month period being referred to as the "Rental Term."</p></td>
            </tr>
            <tr>
                <td width="15"></td>
                <td width="30">3.2.</td>
                <td width="480"><p>Notwithstanding anything to the contrary set forth in Section 3.1, Tenant and all Occupants must vacate the Premises upon the Termination or such earlier date this Agreement is terminated pursuant to the terms hereof. Either party may terminate by giving the other 30-days written notice of termination in writing pursuant to California <u>Civil Code</u> 1946; however, if the Tenant has resided in the unit for one year or more, than the Landlord must give at least 60-days written notice to terminate tenancy (CC’1946). In addition, Landlord may terminate the tenancy and this Agreement by giving written notice as provided by law.</p></td>
            </tr>
            <tr>
                <td><p><b></b></p></td>
                <td width="300"><p><b><u>4. RENT / PAYMENTS</u></b></p></td>
            </tr>
        </table>
      <table>
            <tr>
                <td width="15"></td>
                <td width="30">4.1.</td>
                <td width="480"><p>"Rent" will mean all monetary obligations of Tenant to Landlord under the terms of this Agreement.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>4.2.</td>
                <td><p>Tenant agrees to pay {!! $data['agreement']['monthly_rent_1'] !!} per month during the Rental Term. Landlord will have the right to increase Rent as permitted by applicable law. All payments made are non-refundable. No refunds will be issued. </p></td>
            </tr>
            <tr>
                <td></td>
                <td>4.3.</td>
                <td><p>If Commencement Date falls on any day other than the day Rent is payable under paragraph 4.5., and Tenant has Paid One full month’s Rent in Advance of Commencement Date, Rent for the second calendar month shall be prorated and Tenant shall pay 1/30th of the monthly rent per day for each day remaining in the prorated second month.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>4.4.</td>
                <td><p>For a rent increase:(a) If the amount of the increase, when added to all other increases during the prior 12 months, is 10% or less of the lowest rent charged during the prior 12 months, Landlord may serve a 30-day rent increase notice; (b) If the amount of the increase, when added to all other increases during the prior 12 months, exceeds 10% of the lowest rent charged during the prior 12- months, Landlord must serve a 60 -day rent increase notice. In either case, if the notice is served by mail, the effective date of the rent increase is extended additional 5 days from the date of mailing. </p></td>
            </tr>
            <tr>
                <td></td>
                <td>4.5.</td>
                <td><p>Rent is payable in advance on or before the first (1<sup>st.</sup>) day of each calendar month and delinquent if not received by the third (3<sup>rd</sup>) day of each calendar month. All payments made by Tenant to Landlord under this Agreement will, without regard to their characterization or earmarking by Tenant, be allocated by Landlord in the following order of priority: (i) to late charges and amounts owed from, including but not limited to, maintenance charge-backs, non-payment of pool service and utility payments by Landlord; (ii) to delinquent Rent; (iii) to current Rent due and payable.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>4.6.</td>
                <td><p>In order to pay Rent and / or other charges as required under this Agreement, Tenant can make payments in the following manner:</p></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <table>
                        <tr>
                            <td width="15"></td>
                            <td width="30"><p>4.6.1.</p></td>
                            <td width="400"><p>For the safety of the manager, all payments are to be made by Cashier’s Check or Money Order.</p></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <table>
                        <tr>
                            <td width="15"></td>
                            <td width="30"><p>4.6.2.</p></td>
                            <td width="400"><p>&nbsp;No Cash or Personal Checks shall be accepted.</p></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <table>
                        <tr>
                            <td width="15"></td>
                            <td width="30"><p>4.6.3.</p></td>
                            <td width="400"><p>Submit money order or cashier’s check at the office or apartment of the manager of the building or at such other place designated in writing by Landlord.</p></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>4.7.</td>
                <td><p>All payments make payable to {!! $data['agreement']['payments_to_1'] !!}.(<u>Property Management Co. "LANDLORD"</u>, <u>Property No.</u>, <u>Unit No.</u>)<br />(phone number is) {!! $data['agreement']['payment_phone_number_1'] !!} at (address) {!! $data['agreement']['rent_payment_address_1'] !!} <br />Rent may be paid personally, between the hours of: <b><u>9:00 AM</u></b> to <b><u>6:00 PM</u></b> on <b><u>Monday to Friday</u></b>.<br><b><u>10:00 AM</u></b> to <b><u>5:00 PM</u></b> on <b><u>Saturday</u></b> and <b><u>Sunday</u></b>.</p>
                    <p>Additional Information<br />{!! $data['agreement']['property_additonal_info_1'] !!}</p>
                <p><b>Tenant's Initial(s) <span id="tnt_initial_1">{!! $data['agreement']['tnt_initial_1'] ? $data['agreement']['tnt_initial_1'] : '' !!} </span></b></p>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>4.8.</td>
                <td><p>Landlord reserves the right to change the payment address to which Rent is to be submitted at Landlord’s sole discretion.</p></td>
            </tr>
            <tr>
                <td><b></b></td>
                <td width="150"><b><u>5. SECURITY DEPOSIT</u></b></td>
            </tr>
        </table>
      <table>
            <tr>
                <td width="15"></td>
                <td width="30">5.1.</td>
                <td width="480"><p>Tenant must pay to Landlord on or before the Commencement Date {!! $data['agreement']['sec_dep_1'] !!} as a security deposit (the "Security Deposit"), which amount will be maintained throughout tenancy.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>5.2.</td>
                <td><p>All or any portion of the Security Deposit may be used, as reasonably necessary to: <b>(i)</b> cure Tenant’s default in payment of Rent (which includes Late Charges, NSF fees or other sums due); <b>(ii)</b> repair damage, excluding ordinary wear and tear, caused by Tenant or by a guest of Tenant or by a pet; <b>(iii)</b> clean the premises, if necessary, upon termination of the tenancy; and <b>(iv)</b> replace or return personal property or appurtenances. <b>THE SECURITY DEPOSIT MUST NOT BE USED BY TENANT IN LIEU OF PAYMENT OF AN ADVANCE RENT, LAST MONTH’S RENT, NOR IS TO BE USED OR REFUNDED PRIOR TO RENTED PREMISES BEING COMPLETELY VACATED BY ALL TENANTS.</b> Within twenty-one (21) days after Tenant vacates the Premises, Landlord will: <b>(i)</b> furnish to Tenant and Occupant an itemized statement indicating the amount of any Security Deposit received and the basis for its disposition and supporting documentation as required by California <u>Civil Code</u> 1950.5(g); and <b>(ii)</b> return any remaining portion of the Security Deposit to Tenant.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>5.3.</td>
                <td><p>Tenant shall promptly upon notice and demand by Landlord, replenish any funds used from the security deposit prior to termination of tenancy.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>5.4.</td>
                <td><p>The Security Deposit will not be returned until the Premises has been vacated by Tenant and all other Occupants, if any, and all keys have been returned to Landlord. Any Security Deposit returned by check will be made out to each Tenant named on this Agreement, and mailed to the address provided by Tenant to Landlord.<br />5.4.1.  Security Deposit return mailing address (Optional): {!! $data['agreement']['sec_dep_return_address_1'] !!}.</p></td>
            </tr>

            <tr>
                <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_1">{!! $data['agreement']['tnt_initial_2'] ? $data['agreement']['tnt_initial_2'] : '' !!} </span></b></p></td>
            </tr>
        </table>
      <table>
            <tr>
                <td width="15"></td>
                <td width="30">5.5.</td>
                <td width="480"><p>No interest will be paid on the Security Deposit unless required by law.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>5.6.</td>
                <td><p>In no event will the total Security Deposit (including any Pet Deposits) exceed the limit set forth in California <u>Civil Code</u> 1950.5.</p></td>
            </tr>
            <tr>
                <td><p><b></b></p></td>
                <td width="100"><p><b><u>6. UTILITIES</u></b></p></td>
            </tr>
        </table>
      <table>
            <tr>
                <td width="15"></td>
                <td width="30">6.1.</td>
                <td width="480"><p>Tenant agrees to pay for all utilities and services serving the Premises. If any utilities are not separately metered, Tenant must pay Tenant's proportional share, as reasonably determined and directed by Landlord. If utilities are separately metered, Tenant must place utilities in Tenant's name on or before <b>Commencement Date</b>. Tenant must pay any cost for conversion from existing utilities service providers. Landlord is only responsible for installing and maintaining one useable telephone jack in the Premises.</p></td>
            </tr>
      </table>
      <table> 
            <tr>
                <td width="15"></td>
                <td width="30">6.2.</td>
                <td width="480">
                    Selected below services Tenant agrees to pay directly to service provider:<br />
                    <b>Electricity ({!! $data['agreement']['electricity_service_1'] !!}), Gas ({!! $data['agreement']['gas_service_1'] !!}), Water / Sewer ({!! $data['agreement']['water_service_1'] !!}), Trash ({!! $data['agreement']['garbage_service_1'] !!}), Other<br />{!! $data['agreement']['other_service_1'] !!}</b><br />
                      Or reimburse Landlord:<br>
                      <b>Electricity ({!! $data['agreement']['electricity_reimburse_1'] !!}), Gas ({!! $data['agreement']['gas_reimburse_1'] !!}), Water / Sewer ({!! $data['agreement']['water_reimburse_1'] !!}), Trash ({!! $data['agreement']['garbage_reimburse_1'] !!}), Other<br /> {!! $data['agreement']['other_reimburse_1'] !!}</b>
                    
                </td>
            </tr>
            <tr>
                <td></td>
                <td>6.3.</td>
                <td><p>Tenant is required to show proof of utility commencement prior to move-in. Utilities that require proof are electricity, gas, and water, sewer, and trash services. Tenant must provide Landlord with current account numbers for all applicable utilities prior to move-in.</p></td>
            </tr>
            <tr>
                <td></td>
                <td>6.4.</td>
                <td><p>Notwithstanding anything to the contrary in this Section, Landlord may, at its option and in its sole discretion, elect to contract directly with the utility service providers providing utilities to the Premises, including without limitation, water, trash and refuse removal, sewer, natural gas and electricity. In the event Landlord elects to contract for utilities directly with the service providers then Tenant must reimburse Landlord for all charges incurred by Landlord (all such charges being referred to in this Agreement as the "Utility Charges") within fifteen (15) days of Landlord's request therefore accompanied by an invoice documenting such Utility Charges owed by Tenant. The Utility Charges will be deemed "Rent" under this Agreement so that Tenant's failure to pay the Utility Charges when due will constitute an Event of Default under <b>Section 35</b>, below, entitling Landlord to the remedies set forth in <b>Section 36</b>, below.</p></td>
            </tr>
            <tr>
              <td width="480"><p><b><u>&nbsp;7. PARKING</u></b></p></td>
            </tr>
      </table>
      <table>
        <tr>
              <td width="15"></td>
              <td width="30">7.1.</td>
              <td width="480"><p>Parking is permitted on the Premises in the driveway, garage, or designated spaces only. The driveway, garage, and/or parking space(s) are to be used only for parking properly licensed and operable motor vehicles, no trailers, boats, campers, buses or trucks (other than pick-up trucks). Parking space(s) are to be kept clean. Vehicles leaking oil, gas or other motor vehicle fluids must not be parked on the Premises. Mechanical work or storage of inoperable vehicles is not permitted in the driveway, designated parking space(s) or elsewhere on the Premises. Parking is not permitted in Fire Lanes, or where other vehicle access is impaired, blocking trash receptacle, or other area marked NO PARKING.</p></td>
            </tr>
            <tr>
              <td width="15"></td>
              <td width="30">7.2.</td>
              <td width="480"><p>The Right to Parking <b> is ({!! $data['agreement']['include_parking_1'] !!})</b>, <b>is not</b> ({!! $data['agreement']['not_include_parking_1'] !!}) included in the Rent charged pursuant to paragraph 4. {!! $data['agreement']['parking_notes_1'] !!}</p></td>
            </tr>
            <tr>
                <td width="480"><p><b><u>&nbsp;8. STORAGE</u></b></p></td>
            </tr>
      </table>
      <table>
          <tr>
              <td width="15"></td>
              <td width="30">8.1.</td>
              <td width="480"><p>Tenant must store only personal property Tenant owns, and must not store property claimed by another or in which another has any right, title or interest. Tenant must not store any flammable materials, explosives, hazardous waste or other inherently dangerous material, or illegal substances.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>8.2.</td>
              <td><p>The right to separate storage space <b>is ({!! $data['agreement']['include_storage_1'] !!})</b>, <b>is not ({!! $data['agreement']['not_include_storage_1'] !!})</b> included in the Rent charged pursuant to paragraph 4. {!! $data['agreement']['storage_notes_1'] !!}</p></td>
            </tr>
      </table>
    <table>
        <tr>
            <td width="480"><p><b><u>&nbsp;9. PETS</u></b></p></td>
        </tr>
    </table>
    <table>
            <tr>
              <td width="15"></td>
              <td width="30">9.1.</td>
              <td width="480"><p>Unless otherwise provided in California <u>Civil Code</u> 54.2, or other law, no animal or pet must be kept on or about the Premises without Landlord's prior written consent. Landlord hereby consents to the following pets being kept at the Premises: <b>Yes</b> ({!! $data['agreement']['include_pets_1'] !!}) ,<b>No</b> ({!! $data['agreement']['not_include_pets_1'] !!}): {!! $data['agreement']['included_pets_1'] !!}.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>9.2.</td>
              <td><p>If none specified, no pets are allowed unless otherwise permitted by the above-referenced statute or other applicable law. If Tenant is permitted to keep a pet at the Premises, Tenant must pay a supplemental Pet Fee in the amount of Three Hundred Dollars (<b>$300</b>), for each pet. Additional pets post move-in may be permitted with Landlord's prior written approval and an additional Pet Fee will apply to each additional pet. If Landlord learns that Tenant has added additional pets or has "visiting" pets that have not been pre-approved by Landlord, then Tenant must pay within seven (7) days of Landlord's demand, a Pet Fee in the amount of One Thousand Dollars ($1,000) per additional, not previously approved pet. Tenant's failure to timely pay such additional Pet Fee will constitute an Event of Default hereunder.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>9.3.</td>
              <td><p>Tenant will provide proof of vaccinations, and copy of local animal control licenses.</p></td>
            </tr>
    </table>
    <table>
        <tr>
            <td width="480"><p><b><u>&nbsp;10. MOVE-IN COSTS</u></b></p></td>
        </tr>
    </table>
    <table>
        <tr>
            <td width="15"></td>
            <td width="30"></td>
            <td width="480"><b>Move In Date: </b>{!! $data['agreement']['move_in_date'] !!}&nbsp;&nbsp;&nbsp;&nbsp;<b>Prorate:</b> {!! $data['agreement']['prorate'] !!}</td>
        </tr>
        <tr>
            <td width="15"></td>
            <td width="30">10.1.</td>
            <td width="480"><p>The following move-in funds must be paid to Landlord by Cashier’s Check or Money Order, upon Rental Agreement execution:</p></td>
        </tr>
        <tr>
            <td></td>
            <td>10.2.</td>
            <td width="400"><p>Security Deposit</p></td>
            <td><p><span class="cost">{!! $data['agreement']['sec_dep_2'] !!}</span></p></td>
        </tr>
        <tr>
            <td></td>
            <td>10.3.</td>
            <td width="400"><p>First Month’s Rent</p></td>
            <td><p><span class="cost">{!! $data['agreement']['monthly_rent_2'] !!}</span></p></td>
        </tr>
        {!! $data['agreement']['next_month_rent'] !!}
        {!! $data['agreement']['additional_costs_1'] !!}
        <tr>
            <td></td>
            <td width="430"><p><b>TOTAL DUE UPON LEASE EXECUTION</b></p></td>
            <td><p><span class="cost">{!! $data['agreement']['total_cost_1'] !!}</span></p></td>
        </tr>
    </table>
    <table>
<!--        <tr>
            <td width="15"></td>
            <td width="30">10.4.</td>
            <td width="400"><p>Next Month’s Rent (if applicable)</p></td>
            <td><p><span class="cost">{!! $data['agreement']['next_month_rent_1'] !!}</span></p></td>
        </tr>-->
        <tr>
          <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_2">{!! $data['agreement']['tnt_initial_3'] ? $data['agreement']['tnt_initial_3'] : '' !!}</span></b></p></td>
        </tr>
        <tr>
         <td width="15"><p><b></b></p></td>
         <td width="250"><p><b><u>LATE FEE; RETURNED CHECK FEE</u></b></p></td>
        </tr>
    </table>
      <table>
        <tr>
            <td width="15"></td>
            <td width="30">10.4.</td>
            <td width="480"><p>Tenant acknowledges either late payment of Rent or issuance of a returned check may cause Landlord to incur costs and expenses, the exact amounts of which are extremely difficult and impractical to determine. These costs may include but are not limited to processing, enforcement, accounting expenses, and late charges imposed on Landlord. If any installment of Rent due from Tenant is not received by Landlord within <b>5 calendar days after</b> the date due, or if a check is returned Tenant must pay to Landlord <b><u>$75.00</u></b> as a "Late Fee". Additionally, Landlord will impose a <b><u>$50.00</u></b> Non-Sufficient Funds Fee ("NSF Fee") for any returned check.</p></td>
        </tr>
        <tr>
            <td></td>
            <td>10.5.</td>
            <td><p>If Tenant pays Rent late on two (2) instances or more then Landlord reserves the right to increase Late Fee per Landlord’s discretion.</p></td>
        </tr>
        <tr>
            <td></td>
            <td>10.6.</td>
            <td><p>Landlord and Tenant agree that the aforementioned fees represent a fair and reasonable estimate of the costs Landlord may incur by reason of Tenant's late or NSF payment. Any Late Fee or NSF Fee due must be paid with the current installment of Rent and all such fees incurred by Tenant will be deemed additional Rent. Landlord's acceptance of any Late Fee or NSF Fee will not constitute a waiver as to any default of Tenant. Landlord's right to collect a Late Fee or NSF Fee will not be deemed an extension of the date Rent is due or prevent Landlord from exercising any other rights and remedies under this Lease and as provided by law.</p></td>
        </tr>
      </table>
<table>
        <tr>
          <td style="white-space: nowrap;"><p><b><u>11. ATTORNEY'S FEES / WAIVER OF JURY TRIAL</u></b></p></td>
        </tr>
        <tr>
            <td width="525"><p class="left-padding-p">If any legal action or proceeding is brought by either party to enforce any part of this Agreement, the prevailing party shall recover, in addition to all other relief, actual attorney�s fees and cost pursuant to California <u>Civil Code</u> 1717, but not to exceed $750.00. Recognizing that a Jury Trial is both time consuming and expensive,<u>LANDLORD AND TENANT HEREBY WAIVE TRIAL BY JURY IN ANY
ACTION, PROCEEDING OR COUNTERCLAIM BROUGHT BY EITHER OF THE PARTY HERETO AGAINST THE OTHER IN RESPECT OF ANY
MATTER ARISING OUT OF OR IN CONNECTION WITH THIS AGREEMENT OR THE USE, OR THE OCCUPANCY OF THE PREMISES HEREIN.</u></p></td>
        </tr>
        <tr>
            <td>
              <p><b>Landlord:</b>{!! $data['agreement']['landlord_signature_1'] !!}</p>
              <p>{!! $data['agreement']['tnt_signature_'][0] !!}</p>
            </td>
        </tr>
      </table>
      <table>
        <tr>
          
            <td style="white-space: nowrap;"><p><b>&nbsp;<u>12. CREDIT REPORT</u></b></p></td>
        </tr>
        <tr>
            <td width="525"><p class="left-padding-p">As required by law, you are hereby notified that negative credit reports reflecting on your credit record may be submitted to a credit reporting agency and/or your credit may be checked periodically.</p></td>
        </tr>
        <tr>  
            <td style="white-space: nowrap;"><p><b><u>&nbsp;13. CONDITION OF PREMISES</u></b>Tenant has examined the Premises,including smoke/carbon monoxide detector(s) and, if any, all furniture, furnishings, appliances, landscaping and fixtures, and Tenant acknowledges these items are clean and in operable condition. With the following exceptions, if any are so specified: <b><u>see MIMO Inspection Checklist.</u></b></p></td>
        </tr>
      </table>
        <table>
            <tr>
             
                <td width="350"><p><b><u>&nbsp;14. CARBON MONOXIDE DETECTOR NOTICE</u></b></p></td>
            </tr>
        </table>
        <table>
            <tr>
              <td width="15"></td>
              <td width="30">14.1.</td>
              <td width="475"><p>INSTALATION OF CARBON MONOXIDE DETECTORS</p></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><p>A. <b>Requirements</b>: California law (Health and Safety Code sections 13260 to 13263 and 17296 to 17296.2) requires that as of July 1, 2011, all existing single-family dwellings have carbon monoxide detectors installed and that all other types of dwelling units intended for human occupancy have carbon monoxide detectors installed on or before January 1, 2013. The January 1, 2013 requirement applies to a duplex, lodging house, dormitory, hotel, condominium, time-share and apartment, among others.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>14.2.</td>
              <td><p><b>Exceptions</b>: The law does not apply to a dwelling unit which does not have any of the following: a fossil fuel burning heater or appliance, a fireplace, or an attached garage. The law does not apply to dwelling units owned, leased or rent by the State of California, the Regents of the University of California or local government agencies. Aside from these three types, there are <b>no other exemptions</b> from the installation requirement; it applies to all dwellings, be they owned, leased or rented by individual banks, corporations, or other entities.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>14.3.</td>
              <td><p><b>DISCLOSURE OF CARBON MONOXIDE DETECTORS:</b> The Health and Safety Code does not require a disclosure regarding the existence of carbon monoxide detectors in a dwelling.</p></td>
            </tr>
            <tr>
              
                <td width="300"><p><b><u>&nbsp;15. MAINTENANCE</u></b></p></td>
            </tr>
          </table>
<table>  
            <tr>
              <td width="15"></td>
              <td width="35">15.1.</td>
              <td width="480"><p>As additional consideration for the amount of Rent being charged hereunder, Tenant, at Tenant's own expense, is responsible for keeping the Premises in good condition, order and repair (as reasonably determined by Landlord), subject to reasonable and customary wear and tear. Tenant's obligations under this Section do not include maintenance and repairs to the structural elements of the Premises or the utility systems serving the Premises, which include the roof structure and membrane, foundation, water and sewer main lines serving the Premises, heating and ventilation systems, water heaters, electrical systems and appliances.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.2.</td>
              <td><p>Tenant must properly use, operate and safeguard the Premises, including if applicable, any landscaping (including irrigation), furniture, furnishings and appliances, and all mechanical, electrical, gas and plumbing fixtures, and keep them and the Premises clean, maintained free of trash, well-ventilated and in a manner that does not create a safety hazard to any person within the Premises or neighborhood.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.3.</td>
              <td><p>Tenant is responsible for checking and maintaining all smoke and carbon monoxide detectors. Tenant must immediately notify Landlord, in writing, of any problem, malfunction or damage. Tenant is not permitted to remove or disable smoke/carbon monoxide detectors for any reason.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.4.</td>
              <td><p>Tenant will be charged for all repairs or replacements caused by Tenant, pets, or guests of Tenant, excluding ordinary wear and tear. Tenant will be charged for all damage to the Premises as a result of failure to report a problem in a timely manner. Tenant will be charged for repair of drain blockages or stoppages, unless caused by defective plumbing parts or tree roots invading sewer lines.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.5.</td>
              <td><p>Tenant will be charged for all damage to the Premises resulting from Tenant's failure to maintain the Premises as required in this Agreement, including, without limitation, any drain blockages or stoppages.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.6.</td>
              <td><p>Tenant's failure to maintain any item for which Tenant is responsible will give Landlord the right to hire a vendor of its choosing to perform such maintenance and charge Tenant to cover the cost of such maintenance. Tenant's failure to maintain or repair any item for which Tenant is responsible will also be deemed a default of the Agreement and Landlord may provide Tenant with a notice to cure such default within fourteen (14) days. If Tenant fails to cure such default within fourteen (14) days, then Tenant will be deemed in breach of this Agreement and Landlord will have all remedies available to Landlord pursuant to this Agreement and under the law of the State of California.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.7.</td>
              <td><p>Tenant shall be responsible for maintaining the cleanliness of the unit including window coverings, and carpets if applicable. Tenant agrees to keep the premises in good repair and free from trash and unsightly material, and to immediately notify Landlord in writing of any defects or dangerous conditions in or about the premises. Tenant shall reimburse Landlord for the cost to repair damage by Tenant through misuse or neglect including screens, clogs and windows.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.8.</td>
              <td><p>Tenant must maintain the yard and landscaping as set forth in the <b>"Yard & Landscape Maintenance Addendum attached hereto."</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.9.</td>
              <td><p>Landlord will have no duty to maintain, repair or replace any appliances owned or placed on the Premises by Tenant. In addition, Landlord will have no duty to maintain, repair or replace the following, if any: {!! $data['agreement']['replace_appliances_1'] !!}
                </p></td>
            </tr>
            <tr>
              <td></td>
              <td>15.10.</td>
              <td><p><b>Tenant agree and acknowledge that Tenant's maintenance obligations and duties as set forth in this Section are a material consideration and inducement for Landlord to enter into this Agreement and Landlord would not agree to enter this Agreement but for Tenant's maintenance obligations and duties as set forth herein. See attached "Cleaning & Maintenance Guidelines."</b></p>
              </td>
            </tr>
            <tr>
                <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_4">{!! $data['agreement']['tnt_initial_4'] ? $data['agreement']['tnt_initial_4'] : '' !!}</span></b></p></td>
            </tr>
            <tr>
                <td width="525"><p><b><u>&nbsp;16. INDEMNIFICATION</u></b><br />Tenant agrees to indemnify, defend (with counsel reasonably acceptable to Landlord) and hold harmless Landlord, <b><u>"Property Management Company", (see section 4.7), Landlord, the owner of the Premises if other than the Landlord ("Premises Owner"), all affiliates of Landlord, Premises Owner, and Property Management Company and each of their respective</u></b>, agents, employees, directors, officers, contractors, and subcontractors (collectively, "Landlord Related Parties") from and against any and all claims, demands, losses, liabilities, causes of action, suits, judgments, damages, costs and expenses (including attorneys’ fees) (collectively, "Claims"), arising from any occurrence in or about the Premises, the use and occupancy of the Premises, or from any activity, work, or thing done, permitted or suffered by Tenant, its agents, employees, contractors, shareholders, partners, invitees, sub Tenants or assignees in or about the Premises or due to any other act or omission of Tenant, its sub Tenants, assignees, invitees, employees, contractors and agents, or from Tenant’s failure to perform its obligations under the Agreement (other than any loss arising from the sole or gross negligence of Landlord or its agents), including, but not limited to, occasions when such loss is caused or alleged to be caused by the joint, comparative, or concurrent negligence or fault of Landlord or its agents, and even if any such claim, cause of action, or suit is based upon or alleged to be based upon the strict liability of Landlord or its agents. Without limitation, this indemnity provision is intended to indemnify Landlord and its agents against the consequences of their own negligence or fault as provided above when Landlord or its agents are jointly, comparatively, or concurrently negligent with Tenant. This indemnity provision shall survive termination or expiration of the Agreement.</p></td>
            </tr>
            <tr>
                <td width="525"><p><b><u>&nbsp;17. NEIGHBORHOOD CONDITIONS</u></b><br />Tenant is advised to satisfy him or herself as to neighborhood or area conditions, including schools, proximity and adequacy of law enforcement, crime statistics, proximity of registered felons or offenders, fire protection, other governmental services, availability, adequacy and cost of any wired, wireless internet connections or other telecommunications or other technology services and installations, proximity to commercial, industrial or agricultural activities, existing and proposed transportation, construction and development that may cause or affect noise, view, or traffic, airport noise, noise or odor from any source, wild and domestic animals, other nuisances, hazards or circumstances, cemeteries, facilities and condition of common areas, conditions and influences of significance to certain cultures and/or religions, and personal needs, requirements and preferences of Tenant.</p></td>
            </tr>
          </table>
                <table>
            <tr>
             
                <td width="525"><p><b><u>&nbsp;18. NO SMOKING</u></b><br />No smoking is allowed on the Premises. If smoking does occur on the Premises, then <b>(i)</b> Tenant is responsible for all damage caused by the smoking including, but not limited to, stains, burns, odors and removal of debris; <b>(ii)</b> Tenant is in breach of this Agreement; <b>(iii)</b> Tenant, his or her guests, and all others may be required to leave the Premises; and <b>(iv)</b> Tenant acknowledges that in order to remove odor caused by smoking, Landlord may need to replace carpet and drapes and paint the entire Premises regardless of when these items were last cleaned or replaced, and such replacement and cleaning will be performed at Tenant's sole cost and expense.</p></td>
            </tr>
              <tr>
                
                  <td width="300"><p><b><u>&nbsp;19. RULES; REGULATIONS; CODE VIOLATION</u></b></p></td>
              </tr>
          </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">19.1.</td>
              <td width="480"><p>Tenant, Resident, Occupant agrees not to violate any law, statute, or ordinance, nor to commit, suffer or permit any waste, or nuisance in, on, or about the said premises, or in any way to annoy, molest or interfere with any other tenant or occupants of the building, and neighboring building, nor to use in a wasteful or unreasonable or hazardous manner any of the utilities furnished by Landlord, not to maintain any mechanical, electrical or other appliance or device operated by any said utilities except as herein listed and specifically approved by Landlord in writing. Tenant shall reimburse Landlord for any fines or charges imposed by city, county, or other authorities, due to any violation by Tenant, or guests or licensees of Tenant, and Landlord shall have the right to deduct such amounts from the security deposit described in section 5.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>19.2</td>
              <td><p>Tenant agrees to comply with all Landlord rules and regulations that are at any time posted on the Premises or delivered to Tenant. Tenant must not, and must ensure that guests and licensees of Tenant do not, disturb, annoy, endanger or interfere with neighbors, or use the Premises for any unlawful purposes, under federal, state, or local law including, but not limited to, using, manufacturing, cultivating, selling, storing or transporting illicit drugs or other contraband, or violate any law or ordinance, or commit a waste or nuisance on or about the Premises.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>19.3.</td>
              <td><p>Tenant has been provided with, and acknowledges receipt of, a copy of the rules and regulations. See attached: <b>Addendum to Rental Agreement Apartment / House Rules.</b></p></td>
            </tr>
            <tr>
              <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_5">{!! $data['agreement']['tnt_initial_5'] ? $data['agreement']['tnt_initial_5'] : '' !!}</span></b></p></td>
            </tr>
            <tr>
             
                <td width="480"><p><b><u>&nbsp;20. CONDOMINIUM; PLANNED UNIT DEVELOPMENT (If Applicable)</u></b></p></td>
            </tr>
          </table>
<table>
            <tr>
              <td width="15"></td>
              <td width="30">20.1.</td>
              <td width="480"><p>The Premises are a unit in a condominium, planned unit development, common interest subdivision or other development governed by a homeowners’ association ("HOA"). The name of the HOA is {!! $data['agreement']['hoa_name_1'] !!}. Tenant agrees to comply with all HOA covenants, conditions and restrictions, bylaws, rules and regulations and decisions ("HOA Rules"). Tenant shall reimburse Landlord for any fines or charges imposed by HOA or other authorities, due to any violation by Tenant, or the guests or licensees of Tenant or Landlord shall have the right to deduct such amounts from the security deposit, Section 5.1.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>.</td>
              <td><p>If applicable, Tenant is required to pay a fee to the HOA to gain access to certain areas wihin the development such as but not necessarily including or limited to the front gate, pool, and recreational facilities. If not specified in Section 6, Tenant is solely responsible for payment and satisfying an HOA requirements prior to or upon or after the Commencement Date.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>20.2.</td>
              <td><p>Landlord shall provide Tenant with a copy of the HOA Rules within {!! $data['agreement']['hoa_days_1'] !!} Days.</p></td>
            </tr>
            <tr>
                <td colspan="3" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_6">{!! $data['agreement']['tnt_initial_6'] ? $data['agreement']['tnt_initial_6'] : '' !!}</span></b>: Tenant has been provided with, and acknowledges receipt of a copy of the HOA Rules.</p></td>
            </tr>
            <tr>
             
                <td width="525"><p><b><u>&nbsp;21. ALTERATIONS; REPAIRS</u></b><br />Unless otherwise specified by law or in this Agreement, without Landlord's prior written consent, (i)Tenant must not make any alterations or improvements or material repairs in or about the Premises including: painting, wallpapering, adding or changing locks, installing antenna or satellite dish(es), placing signs, displays or exhibits, or using screws, fastening devices, large nails or adhesive materials; (ii) Landlord will not be responsible for the costs of alterations or repairs made by Tenant; (iii) Tenant must not deduct from Rent the costs of any repairs, alterations or improvements; and (iv) any deduction made by Tenant will be considered unpaid Rent.</p></td>
            </tr>
            <tr>
                <td width="300"><p><b><u>&nbsp;22. KEYS AND LOCKS</u></b></p></td>
            </tr>
          </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">22.1.</td>
              <td width="480"><p>Tenant acknowledges receipt of {!! $data['agreement']['premise_keys_1'] !!} Key(s) to Premises, {!! $data['agreement']['mailbox_keys_1'] !!} Key(s) to mailbox. </p></td>
            </tr>
            <tr>
              <td></td>
              <td>22.2.</td>
              <td><p>Tenant acknowledges that locks to the Premises have been re-keyed.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>22.3.</td>
              <td><p>If tenant re-keys existing locks or opening devices, Tenant must immediately deliver copies of all keys to Landlord. Tenant must pay all cost and charges related to loss of any keys or opening devices. Tenant may not remove locks, even if installed by Tenant.</p></td>
            </tr>
            <tr>
              
                <td width="300"><p><b><u>&nbsp;23. RIGHT OF ENTRY</u></b></p></td>
            </tr>
          </table>
        <table>
            <tr>
              <td width="15"></td>
              <td width="30">23.1.</td>
              <td width="480"><p>Landlord reserves the right to himself or his/her agent to entry said Premises, Tenant must make Premises available to Landlord or Landlord’s representative for entry, in case of emergency, to inspect the Premises to confirm that Tenant is properly maintaining the Premises pursuant to Section 15 above, to make necessary or agreed repairs (including, but not limited to, installing, repairing, testing, and maintaining smoke detectors and carbon monoxide devices, and bracing, anchoring or strapping water heaters, or repairing dilapidation relating to the presence of mold), decorations, alterations, or improvements, supplying necessary or agreed services or exhibit the dwelling to prospective purchasers, mortgagees, lenders, appraisers, tenants, workmen or contractors or when any tenant has abandoned or surrendered the premises or pursuant to court order or other (collectively "interested Persons"). Tenant agrees that Landlord, Broker and interested Persons may take photos of the Premises.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>23.2.</td>
              <td><p>Except in cases of emergency or abandonment, entry will be made during normal business hours and landlord shall give the Tenant reasonable notice of intent to enter premises of no less than 24 hours pursuant to California <u>Civil Code</u> 1954. <b>Tenant agrees not to change any lock or locking device to said premises without the prior written consent of the Landlord, but Tenant will, upon demand, furnish Landlord with the keys for the purpose of making duplicates thereof.</b> Upon demand by Landlord, Tenant shall temporarily vacate the premises for a reasonable period of time to allow pest or vermin control work to be done. Tenant shall comply with all instructions, forthwith, from pest controller, fumigator and/or exterminator regarding the preparation of the premises for the work, include the proper bagging and storage of food perishables and medicine. Tenant will only be entitled to a credit of Rent equal to the per diem Rent for the period of time Tenant is required to vacate the Premises.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>23.3.</td>
              <td><p>No notice is required: (i) to enter in case of an emergency; (ii) if the Tenant is present and consents at the time of entry; or (iii) if the Tenant has abandoned, has appeared to have abandoned or surrendered the Premises. No written notice is required if Landlord and Tenant orally agree to an entry for agreed services or repairs if the date and time of entry are within one week of the oral agreement. Tenant's failure or refusal to grant Landlord (or Landlord's representatives) access to the Premises as provided in this Section will be a default by Tenant under this Agreement.</p></td>
            </tr>
            <tr>
              
                <td width="480"><p><b><u>&nbsp;24. SIGNS</u></b><br />Tenant authorizes Landlord to place FOR SALE/FOR LEASE signs on the Premises.</p></td>
            </tr>
            <tr>
              
                <td width="525"><p><b><u>&nbsp;25. ASSIGNMENT SUBLETTING</u></b><br />Tenant must not sublet all or any part of the Premises, or assign or transfer this Agreement or any interest in it, without Landlord's prior written consent, which consent may be given or withheld in Landlord's sole discretion. Unless such consent is obtained, any assignment, transfer or subletting of the Premises or this Agreement or tenancy, by voluntary act of Tenant, operation of law or otherwise, will, at the option of Landlord terminate this Agreement. Any proposed assignee, transferee or sub-Tenant must submit to Landlord an application and credit information for Landlord's approval and, if approved, sign a separate written agreement with Landlord and Tenant. If no such information is provided or if the same is provided in an incomplete fashion, Tenant agrees that it will be reasonable for Landlord to withhold its consent to the proposed assignee or sub-Tenant. Landlord's consent to any one assignment, transfer or sublease, will not be construed as consent to any subsequent assignment, transfer or sublease and does not release Tenant of Tenant's obligations under this Agreement.</p></td>
            </tr>
            <tr>
              
                <td width="525"><p><b><u>&nbsp;26. BUSINESS USE</u></b><br />Tenant must not utilize the Premises or any part thereof for operation of a business or for other commercial enterprise without Landlord's prior written consent, nor will Tenant utilize the Premises for any purpose or in any manner which violates applicable state, federal or local law or the covenants, conditions, or rules of any applicable homeowners association. Tenant's failure to abide by the terms of this Section will result in an Event of Default under this Agreement.</p></td>
            </tr>
            <tr>
              
                <td width="300"><p><b><u>&nbsp;27. JOINT AND SEVERAL LIABILITY</u></b></p></td>
            </tr>
          </table>
        <table>
            <tr>
              <td width="15"></td>
              <td width="30">27.1.</td>
              <td width="480"><p>The undersigned Tenant(s) whether or not in actual possession of premises, are jointly and severally liable for all rent incurred during the term of this Agreement, and for all damages to the demised premises caused or permitted by Tenants, their guests and invitees. Any breach or abandonment by any one or more of the tenants shall not terminate the Agreement nor shall it relieve the remaining Tenants from fulfilling the terms of the Agreements.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>27.2.</td>
                  <td><p>If there is more than one individual who is a "Tenant" under this Agreement, then each individual will be individually and completely (i.e. jointly and severally) responsible and liable for the full and complete performance of all obligations of Tenant under this Agreement, jointly with every other Tenant, and individually, whether or not in possession.</p></td>
            </tr>
            <tr>
              
                <td width="525"><p><b><u>&nbsp;28. LEAD-BASED PAINT</u></b><br />The Premises may have been constructed prior to 1978. Housing built before 1978 may contain lead-based paint. Lead from paint, paint chips, and dust can pose health hazards if not managed properly. Lead exposure is especially harmful to young children and pregnant women. Before renting pre-1978 housing, Landlord must disclose the presence of lead-based paint and/or lead-based paint hazards in the dwelling. Tenant must also receive a federally approved pamphlet on lead poisoning prevention. In accordance with federal law, Landlord provided and Tenant acknowledges receipt of the disclosures on the attached <b>Disclosure of Information on Lead-Based Paint and/or Lead-Based Paint Hazards.</b></p></td>
            </tr>
            <tr>
                <td colspan="3" width="480"><p><b>Tenant’s Initial(s) <span id="tnt_initial_7">{!! $data['agreement']['tnt_initial_7'] ? $data['agreement']['tnt_initial_7'] : '' !!}</span></b><br />Tenant’s acknowledgement, Tenant has received a copy of the pamphlet "Protect Your Family From Lead In Your Home" and copies of documents listed above if any. Tenant agrees to promptly notify Landlord of any deteriorated and/or peeling paint.</p></td>
            </tr>
            <tr>
              
                <td width="525"><p><b><u>&nbsp;29. ASBESTOS</u></b><br />The Premises may contain asbestos, a substance known to the State of California to cause cancer. In the event the Premises contains asbestos, disturbance or damage to certain interior surfaces may increase the potential exposure to this substance. Tenant, Occupant, and their guests, must not take or permit any action which in any way damages or disturbs the ceiling in the Premises or any part thereof, including without limitation: (i) piercing the surface of the ceiling by drilling or any other method; (ii) hanging plants, mobiles or other objects from the ceiling; (iii) attaching any fixtures to the ceiling; (iv) allowing any objects to come in contact with the ceiling; (v) permitting water or any liquid, other than ordinary steam condensation, to come into contact with the ceiling; (f) painting, cleaning or undertaking any repairs of any portion of the ceiling; (g) replacing light fixtures; (h) undertaking any activity which results in building vibration which may cause damage to the ceiling; (i) or altering or disturbing the heating and ventilation system serving the Premises, including without limitation, any ducting connected thereto. Tenant must notify Landlord and its agents immediately in writing (j) if any damage to or deterioration of the ceiling in the Premises or any portion thereof, including without limitation flaking, loose, cracking, hanging or dislodged material, water leaks, or stains in the ceiling, or (k) upon the occurrence of any of the events described above in this paragraph.</p></td>
            </tr>
            
          </table>
              <table>     
            <tr>
             
                <td width="525"><p><b><u>&nbsp;30. MEGAN’S LAW DATABASE DISCLOSURE</u></b><br />Notice: Pursuant to Section 290.46 of the Penal Code, information about specified registered sex offenders is made available to the public via an Internet Web site maintained by the Department of Justice at <b>www.meganslaw.ca.gov</b>. Depending on an offender's criminal history, this information will include either the address at which the offender resides or the community of residence and ZIP Code in which he or she resides. (Neither Landlord nor Brokers, if any, are required to check this website. If Tenant wants further information, Tenant should obtain information directly from this website.)</p></td>
            </tr>
            <tr>
              
                <td width="525"><p><b><u>&nbsp;31. PROPOSITION 65 WARNING</u></b><br />The State of California has determined certain chemicals commonly found in and around residences are known to cause cancer and birth defects or other reproductive harm. These can be found in California health & safety code section 25249.6. Among such chemicals are second hand cigarette smoke, alcohol, lead paint, and asbestos. Tenant is aware of such chemicals and the dangers they pose.</p></td>
            </tr>
            <tr>
                <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_8">{!! $data['agreement']['tnt_initial_8'] ? $data['agreement']['tnt_initial_8'] : '' !!}</span></b></p></td>
            </tr>
            <tr>
              
                <td width="525"><p><b><u>&nbsp;32. POSSESSION</u></b><br />If delivery of possession of the Premises by Landlord at the commencement of the Term is delayed, Landlord will not be liable for any damage caused by the delay, nor will this Agreement be void or voidable, but Tenant will not be liable for any Rent until possession is delivered. If Landlord is unable to deliver possession of the Premises on the Commencement Date, such date will be extended to the date on which possession is made available to Tenant. If Landlord is unable to deliver possession within ten (10) calendar days after the agreed Commencement Date, Tenant may terminate this Agreement by giving written notice to Landlord, and will be refunded all Rent, the Security  Deposit, and the Pet Deposit, if any, to the extent the same have been paid to Landlord. Possession is  deemed terminated when Tenant has returned all keys to the Premises to Landlord and has surrendered the Premises free of all occupants and personal property pursuant to Section 34, below.</p></td>
            </tr>
            <tr>
              
                <td width="480"><p><b><u>&nbsp;33. TENANT’S OBLIGATIONS UPON VACATING PREMISES</u></b></p></td>
            </tr>
        </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">33.1.</td>
              <td width="480"><p>Upon termination of this Agreement, Tenant must: (i) give Landlord all copies of all the keys or opening devices to the Premises, including any common areas; (ii) vacate and surrender the Premises to Landlord, empty of all persons; and personal property belonging to Tenant (iii) vacate any/all parking and/or storage space; (iv) clean and deliver the Premises, as specified below, to Landlord in the same condition as received by Tenant and described in this Agreement; (v) remove all debris; and (vi) give written notice to Landlord of Tenant's forwarding address.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>33.2.</td>
              <td><p>All alterations/improvements made by or caused to be made by Tenant, with or without Landlord's consent, become the property of Landlord upon termination of this Agreement. Landlord may charge Tenant for restoration of the Premises to the condition it was in prior to any alterations or improvements.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>33.3.</td>
              <td><p><b>Right to Pre-Move-Out Inspection and Repairs:</b> (i) After giving or receiving notice of termination of a tenancy, or before the end of the Agreement, Tenant has the right to request that an inspection of the Premises take place prior to termination of the Agreement; (ii) If Tenant requests such an inspection, Tenant will be given an opportunity to remedy identified deficiencies prior to termination, consistent with the terms of this Agreement; (iii) Any repairs or alterations made to the Premises as a result of this inspection (collectively, "Repairs") will be made at Tenant's expense and only after obtaining the prior written approval of Landlord; (iv) Repairs may be performed by Tenant or through others, who have adequate insurance and licenses and are approved by Landlord; (v) The work must comply with applicable law, including governmental permit, inspection and approval requirements; (vi) Repairs must be performed in a good, skillful manner with materials of quality and appearance comparable to existing materials; (vii) Tenant must: (a) obtain receipts for Repairs performed by others; (b) prepare a written statement indicating the Repairs performed by Tenant and the date of such Repairs; and (c) provide copies of receipts and statements to Landlord prior to termination. Tenant's Right to Pre-Move-Out Inspection, as specified above, does not apply when the tenancy is terminated pursuant to California <u>Civil Code</u> Procedure § 1161(2), (3) or (4).</p></td>
            </tr>
            <tr>
              <td width="480"><p><b>&nbsp;<u>34. DEFAULT</u></b><br />Tenant will be in default under this Agreement if Tenant: (i) fails to pay Rent when due, or (ii) fails to perform any other obligation or duty of Tenant under this Agreement, which failure to pay or perform continues for three (3) days after the delivery of written notice to Tenant of such default in the manner required by law (an "Event of Default"). Any notice required to be given for Tenant to be in default hereunder will not be deemed to be in addition to any statutorily required notice as long as the notice given hereunder otherwise complies with the terms of the applicable statute.</p></td>
            </tr>
            <tr>
              <td width="480"><p><b>&nbsp;<u>35. REMEDIES</u></b><br />After the occurrence of an event of Default, in addition to any and all other remedies available to Landlord at law or in equity, Landlord will have the right to the following:</p></td>
            </tr>
          </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">35.1.</td>
              <td width="480"><p>The immediate option to terminate this Agreement and all rights of Tenant hereunder by giving Tenant written notice of such intention to terminate, in which event Landlord may recover from Tenant all of the following: (i) unpaid Rent at the time of termination; plus (ii) unpaid Rent for the balance of the Agreement Term; plus (iii) any other amount necessary to compensate Landlord for all the detriment proximately caused by Tenant’s failure to perform his or her obligations under this Agreement or which in the ordinary course of things would be likely to result therefrom, including, but not limited to: brokers’ commissions; the costs of refurbishment, alterations, renovation and repair of the Premises reasonably incurred for the sole purpose of re-letting the Premises; and removal (including the repair of any damage caused by such removal) and storage (or disposal) of Tenant’s personal property, equipment, fixtures, alterations and any other items which Tenant is required under this Agreement to remove but does not remove; plus (iv) at Landlord’s election, such other amounts in addition to or in lieu of the foregoing as may be permitted from time to time by applicable California law; plus (v) the amount of any concessions provided to Tenant by Landlord in connection with this Agreement at the time the parties entered into this Agreement and/or at the time of Tenant moving into the Premises.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>35.2.</td>
              <td><p>In addition to the remedies specified above in this Section, if an Event of Default occurs, Landlord has the remedy described in California <u>Civil Code</u> Section 1951.4 (Landlord may continue rent in effect after Tenant’s breach and abandonment and recover Rent as it becomes due, if Tenant has the right to sublet or assign, subject only to reasonable limitations) and may continue the Agreement in effect after Tenant’s default.</p></td>
            </tr>
            <tr>
              <td width="525"><p><b>&nbsp;<u>36. DAMAGE TO PREMISES</u></b><br />If, by no fault of Tenant, the Premises are totally or partially damaged or destroyed by fire, earthquake, accident or other casualty that render the Premises totally or partially uninhabitable, either Landlord or Tenant may terminate this Agreement by giving the other written notice. Rent will be abated as of the date the Premises become totally or partially uninhabitable. The abated amount will be the current monthly Rent prorated on a 30-day period. If this Agreement is not terminated, Landlord will promptly repair the damage, and Rent will be reduced based on the extent to which the damage interferes with Tenant's reasonable use of the Premises. If damage occurs as a result of an act of Tenant or Tenant's guests, only Landlord will have the right of termination, and no reduction or abatement of Rent will be made.</p></td>
            </tr>
            <tr>
              <td width="525"><p><b>&nbsp;<u>37. INSURANCE</u></b><br />Tenant's and Tenant's guest's personal property and vehicles are not insured by Landlord, the property manager, if any, or, if applicable, HOA, against loss or damage due to fire, theft, vandalism, rain, water, criminal or negligent acts of others, or any other cause. <b>Tenant is advised to carry Tenant's own insurance (so-called "renter's insurance") to protect Tenant from any such loss or damage.</b> Tenant must comply with any requirement imposed on Tenant by Landlord's insurer to avoid: (i) an increase in Landlord's insurance premium (or Tenant must pay for the increase in premium); or (ii) loss of insurance.</p></td>
            </tr>
            <tr>
                <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_9">{!! $data['agreement']['tnt_initial_9'] ? $data['agreement']['tnt_initial_9'] : '' !!}</span></b></p></td>
            </tr>
            <tr>
              <td width="525"><p><b>&nbsp;<u>38. WATERBEDS; AQUARIUMS; WASHING MACHINE</u></b><br />Any waterbeds, or liquid-filled furniture as provided under California <u>Civil Code</u> 1940.5, are not permitted. Aquariums larger than 10 gallons are not permitted. Tenant may not have washing machine in unit if not properly plump for such.</p></td>
            </tr>
            <tr>
              <td width="300"><p><b>&nbsp;<u>39. WAIVER</u></b></p></td>
            </tr>
          </table>
            <table>
            <tr>
              <td width="15"></td>
              <td width="30">39.1.</td>
              <td width="480"><p>No failure of Landlord to enforce any term of this Agreement will be deemed a waiver, nor will any acceptance of a partial payment of Rent be deemed a waiver of Landlord's right to the full amount of Rent owed. The waiver of any breach shall not be construed as a continuing waiver of the same or any subsequent breach.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>39.2.</td>
              <td><p>Waiver by Landlord of a breach of any covenant of this Agreement will not be construed to be a continuing waiver of any subsequent breach. Landlord’s receipt of rent with knowledge of Tenant’s violation of a covenant does not waive his/her rights to enforce any covenant of this Agreement. The invalidity or partial invalidity of any provision of the Agreement shall not render the remainder of the Agreement invalid or unenforceable.</p></td>
            </tr>
            <tr>
              <td width="300"><p><b>&nbsp;<u>40. TENANT ESTOPPEL CERTIFICATE</u></b></p></td>
            </tr>
          </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">40.1.</td>
              <td width="480"><p>Tenant must execute and return a Tenant estoppel certificate delivered to Tenant by Landlord or Landlord's agent within <b>three (3) days</b> after its receipt. Failure to comply with this requirement will be deemed an Event of Default and Tenant's acknowledgment that the Tenant estoppel certificate is true and correct, and may be relied upon by a lender or purchaser.</p></td>
            </tr>
            <tr>
              <td width="300"><p><b>&nbsp;<u>41. REPRESENTATIONS</u></b></p></td>
            </tr>
          </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">41.1.</td>
              <td width="480"><p>Tenant Representation; Obligations Regarding Occupants; Credit: Tenant warrants that all statements in Tenant's rental application are accurate. Landlord requires all occupants 18 years of age or older and all emancipated minors to complete a tenant rental application. Tenant acknowledges this requirement and agrees to notify Landlord when any occupant of the Premises reaches the age of 18 or becomes an emancipated minor. Tenant authorizes Landlord and Broker(s) to obtain Tenant’s credit report periodically during the tenancy in connection with the modification or enforcement of this Agreement. Landlord may cancel this Agreement: <b>(i)</b> before occupancy begins; upon disapproval of the credit report(s), or upon discovering that information in Tenant’s application is false; <b>(ii)</b> After commencement date, upon disapproval of an updated credit report or upon discovering that information in Tenant’s application is no longer true. A negative credit report reflecting on Tenant’s record may be submitted to a credit reporting agency if Tenant fails to fulfill the terms of payment and other obligations under this Agreement.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>41.2.</td>
              <td><p>Landlord Representations: Landlord warrants that, unless otherwise specified in writing, Landlord is unaware of <b>(i)</b> any recorded Notices of Default affecting the Premise; <b>(ii)</b> any delinquent amount due under any loan secured by the Premises; and <b>(iii)</b> any bankruptcy proceeding affecting the Premises.</p></td>
            </tr>
            <tr>
              <td width="300"><p><b>&nbsp;<u>42. MEDIATION</u></b></p></td>
            </tr>
          </table>
          <table>
            <tr>
              <td width="15"></td>
              <td width="30">42.1.</td>
              <td width="480"><p>Consistent with this Section, Landlord and Tenant agree to mediate any dispute or claim arising between them out of this Agreement, or any resulting transaction, before resorting to court action. Mediation fees, if any, will be divided equally among the parties involved. If, for any dispute or claim to which this paragraph applies, any party commences an action without first attempting to resolve the matter through mediation or refuses to mediate after a request has been made, then that party will not be entitled to recover attorney fees, even if they would otherwise be available to that party in any such action.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>42.2.</td>
              <td><p>The following matters are excluded from mediation: (i) an unlawful detainer action; (ii) the filing or enforcement of a mechanic's lien; and (iii) any matter within the jurisdiction of a probate, small claims or bankruptcy court. The filing of a court action to enable the recording of a notice of pending action, for order of attachment, receivership, injunction, or other provisional remedies, will not constitute a waiver of the mediation provision. Each of the parties hereby irrevocably waives any right to trial by jury.</p></td>
            </tr>
            <tr>
              <td></td>
              <td>42.3.</td>
              <td><p>Landlord and Tenant agree to mediate disputes or claims involving Listing Agent, Leasing Agent, or property manager ("Broker"), provided Broker shall have agreed to such mediation prior to, or within a reasonable time after, the dispute or claim is presented to such Broker. Any election by Broker to participate in mediation shall not result in Broker being deemed a party to this Agreement.</p></td>
            </tr>
          </table>
      <table>
    <tr>
      <td width="525"><p><b>&nbsp;<u>43. TIME OF ESSENCE; ENTIRE CONTRACT; CHANGES, ETC.</u></b><br />Time is of the essence. All understandings between the Parties are incorporated in this Agreement. Its terms are intended by the Parties as a final, complete and exclusive expression of their agreement with respect to its subject matter, and may not be contradicted by evidence of any prior agreement or contemporaneous oral agreement. If any provision of this Agreement is held to be ineffective or invalid, the remaining provisions will nevertheless be given full force and effect. Neither this Agreement nor any provision in it may be extended, amended, modified, altered or changed except in writing. This Agreement is subject to California law and will incorporate all changes required by amendment or successors to such law. This Agreement and any supplement, addendum or modification, including any copy, may be signed in two or more counterparts, all of which will constitute one and the same agreement.This Agreement is the joint drafting effort of both parties.Every covenant, term, and provision of this Agreement shall be construed simply according to its fair meaning and not strictly for or against any party (notwithstanding any rule of law requiring an Agreement to be strictly construed against the drafting party). Captions contained in this Agreement in no way define, limit or extend the scope or intent of this Agreement.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>44. MILITARY SERVICE TERMINATION RIGHT</u></b><br />In the event that Tenant, is or hereafter becomes, a member of the United States Armed Forces on extended active duty and receives permanent change of station orders to depart from the area where the Premises are located (which for purposes of this Section means an area in excess of fifty (50) miles from the Premises), or is ordered into military housing, then in any of these events, Tenant may terminate this Agreement upon giving not less than thirty (30) days written notice to Landlord. Tenant must also provide to Landlord a copy of the official orders or a letter signed by Tenant's commanding officer, reflecting the change, which entitles Tenant to the right to terminate this Agreement early under this Section. Security Deposit will be disposed per terms of Section 5 of this Agreement.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>45. SUBORDINATION OF AGREEMENT; ATTORNMENT OF TENANT</u></b><br />This Agreement and Tenant’s interest hereunder are and shall be subordinate, junior and inferior to any and all mortgages, deeds of trust, deeds to secure debt, and similar instruments (collectively, "Mortgage Documents"), now or hereafter placed on the Premises, all advances made under any Mortgage Documents (including, but not limited to, future advances), the interest payable on any Mortgage Documents and any and all renewals, extensions or modifications of any Mortgage Documents. Tenant also hereby agrees to attorn, without any deductions or set-offs whatsoever, to any holder of any Mortgage Documents and any other purchaser at a sale of the Premises by foreclosure or power of sale, or any deed in lieu of any of the foregoing, and recognize such holder or other purchaser, as the case may be, as the Landlord under the Agreement. If requested by Landlord, Tenant agrees to execute and deliver any document requested by Landlord in order to further evidence the subordination and attornment described in this Section.</p></td>
    </tr>
    <tr>
        <td width="525"><p><b>&nbsp;<u>46. Class Action Waiver</u></b><br />Tenant hereby waives and relinquishes the right to consolidate any claims it may have against any of the Landlord Parties arising out of, related to or in connection with this Agreement or the use or occupancy of the Premises with the claims of any other person or entity in any litigation, arbitration or other proceeding, and Tenant hereby agrees not to participate in any class or consolidated action or any form of a class or representative proceeding against Landlord Parties.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>47. ASSIGNMENT BY LANDLORD</u></b><br />Landlord shall have the right to, either voluntarily, involuntarily, by operation of law or otherwise, sell, assign, transfer or hypothecate the Agreement.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>48. SUCCESSORS AND ASSIGNS</u></b><br />All of the covenants, conditions and provisions of the Agreement shall inure to the benefit of Landlord and its respective heirs, personal representatives, successors and assigns.</p></td>
    </tr>
    <tr>
      <td width="300"><p><b>&nbsp;<u>49. LANDLORD LIABILITY</u></b></p></td>
    </tr>
</table>
<table>
    <tr>
      <td width="15"></td>
      <td width="30">49.1.</td>
      <td width="480"><p>Landlord shall not be liable or responsible in any way for injury to any person, or for loss of, or damage to, any article belonging to Tenant located in said premises, or other premises under control of Landlord. No right of storage is given by this Agreement. Landlord shall not be liable for non-delivery or mis-delivery of messages nor shall landlord be liable for and this Agreement shall no be terminated by reason of any interruption of, or interference with, services or accommodation due Tenant, caused by strike, riot, orders of public authorities, acts of other tenants, accident, the making of necessary repairs to the building of which said premises are a part, or any other cause beyond Landlord's control.</p></td>
    </tr>
    <tr>
      <td></td>
      <td>49.2.</td>
      <td><p>Not withstanding anything else that may be construed to the contrary herein (including the Addenda), Landlord’s liability, together with the liability of the Landlord Related Parties, under this Agreement, as well as the use and occupancy of the Premises shall be capped at an amount equal to four (4) times the monthly rental rate payable by the Tenant each month up to a maximum aggregate amount of $10,000. Tenant acknowledges that Landlord has set the monthly rental rate for the unit based on the liability cap in the immediately preceding sentence, and that Landlord is relying on such liability cap in entering into this Agreement.Landlord's liability under this Agreement will be limited to Landlord's unencumbered interest in the Premises. Neither Landlord nor any of its partners, members, officers, directors, agents, employees, shareholders, successors, assigns or pledges, including without limitation, the individual signing this Agreement on Landlord's behalf, will in any way be personally liable under this Agreement, and Tenant hereby waives the right to claim any such personal liability.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>50. SATELLITE DISHES</u></b><br />Tenant may install one satellite dish on the Premises, provided it does not exceed one (1) meter (3.3 feet) in diameter. Tenant's installation must comply with reasonable safety standards and may not interfere with any cable, telephone or electrical systems within or serving the Premises. Installation must be done by a qualified person or company and the satellite dish cannot be installed or affixed to the roof of the Premises. Tenant will have the sole responsibility for maintaining any satellite dish or antenna and all related equipment. Tenant must pay for any damages and for the cost of repairs or repainting which may be reasonably necessary to restore the Premises to its condition prior to the installation of any satellite dish or related equipment. Tenant agrees to hold Landlord harmless, defend and indemnify Landlord against any claims or injuries related to Tenant's installation of a satellite dish. If Tenant installs a satellite dish or antenna prior to satisfying the conditions set forth above, then Tenant shall be deemed in default under the Agreement.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>51. NO ENCUMBRANCES PERMITTED</u></b><br />Tenant has no authority or power to cause or permit any lien or encumbrance of any kind whatsoever, whether created by act of Tenant, operation of law or otherwise, to attach to or be placed upon the Premises. Landlord will have the right at all times to post and keep posted on the Premises any notice which it deems necessary for protection from such liens. Tenant covenants and agrees not to suffer or permit any lien of mechanics or materialmen or others to be placed against the Premises with respect to work or services claimed to have been performed for or materials claimed to have been furnished to Tenant or the Premises, and, in case of any such lien attaching or notice of any lien, Tenant covenants and agrees to cause it to be immediately released and removed of record. Notwithstanding anything to the contrary set forth in this Agreement, if any such lien is not released and removed on or before the date notice of such lien is delivered by Landlord to Tenant, Tenant will be deemed in default hereunder and Landlord, at its sole option, may immediately take all action necessary to release and remove such lien, without any duty to investigate the validity thereof, and all sums, costs and expenses, including reasonable attorneys' fees and costs, incurred by Landlord in connection with such lien will be deemed Rent under this Agreement and will immediately be due and payable by Tenant.</p></td>
    </tr>
    <tr>
      <td width="525"><p><b>&nbsp;<u>52. INDEPENDENT AGENT AND COUNSEL</u></b><br />Tenant agrees and acknowledges that the listing agent and leasing agent exclusively represent Landlord. Tenant is advised to consult its own real estate agent and/or counsel in connection with this Agreement.</p></td>
    </tr>
    <tr>
        <td width="525"><p><b><u>&nbsp; <u>53. ADVERTISING TO LEASE OR SUBLEASE OF PREMISES.</u></u></b><br />It shall be considered a non-curable breach of this Rental Agreement if Tenant or any Occupant, directly or indirectly, (i) advertise in any print or electronic media to lease the Premises or (ii) sublease the Premises for any length of time. By way of example, advertising in AIRBNB would be considered a non-curable breech of this Rental Agreement.</p></td>
    </tr>
    <tr>

        <td style="white-space: nowrap;" width="500"><p><b><u>&nbsp; 54. HAZARD NOTICE:</u></b><br />Pursuant to Government Code Section 8589.45, Tenant may obtain information about hazards, including flood hazards, that may affect the property from the Internet Web site of the Office of Emergency Services athttp://myhazards.caloes.ca.gov/. The Landlord’s insurance does not cover the loss of the Tenant’s personal possessions and it is recommended that the Tenant consider purchasing renter’s insurance and flood insurance to insure his or her possessions from loss due to fire, flood, or other risk of loss. The Landlord is not required to provide additional information concerning the flood hazards to the property and the information provided pursuant to this section is deemed adequate to inform the Resident.
(Check box if applicable) {!! $data['agreement']['has_hazard_1'] !!}  The Premises is located in a special flood hazard area or an area of potential flooding.</p></td>
    </tr>
    <tr>
      <td  width="480" style="white-space: nowrap;"><p><b><u>&nbsp;55. ADDENDA: </u></b></p></td>
    </tr>
    <tr>
      <td width="300"><p><b></b>The following Addenda and exhibits attached and referred to in this Agreement are hereby incorporated herein as fully set forth in (and shall be deemed to be a part of) this Agreement:</p></td>
    </tr>
</table>
<table>
            <tr>
              <td width="15"></td>
              <td width="30">55.1.</td>
              <td width="480"><p>Complaint Procedure Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.2.</td>
              <td><p>Service Procedure Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.3.</td>
              <td><p>Bed Bug Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.4.</td>
              <td><p>Rental Mold and Ventilation Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.5.</td>
              <td><p>Addendum to Rental Agreement Apartment / House Rules</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.6.</td>
              <td><p>Move-in & Move-out (MIMO)</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.7.</td>
              <td><p>Disclosure of Information on Lead-Based Paint and/or Lead-Based Paint Hazards</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.8.</td>
              <td><p>Cleaning & Maintenance Guidelines</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.9. </td>
              <td><p>Notice of Periodic Application of Pesticides by Pest Control Operator Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.10. </td>
              <td><p>Water Conservation Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.11. </td>
              <td><p>Water Sub-metering Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.12. </td>
              <td><p>Renter Insurance Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.13. </td>
              <td><p>Satellite Dish & Antenna Addendum</p></td>
            </tr>
            <tr>
              <td></td>
              <td>55.14. </td>
              <td><p>Crime-Free Lease Addendum (if applicable)</p></td>
            </tr>
            <tr>
                <td></td>
                <td>55.15</td>
                <td><p>No-Nuisance Lease Addendum (if applicable)</p></td>
            </tr>
            <tr>
                <td></td>
                <td>55.16</td>
                <td><p>Yard and Landscape Maintenace Addendum (if applicable)</p></td>
            </tr>
            <tr>
                <td></td>
                <td>55.17</td>
                <td><p>Pool Maintenance and Equipment Addendum (if applicable)</p></td>
            </tr>
<!--            <tr>
              <td></td>
              <td>55.15. </td>
              <td><p>Other: {!! $data['agreement']['other_addenda_1'] !!}</p></td>
            </tr>-->
        </table>
        <h4 style="text-align:center;"><b>SIGNATURE PAGE</b></h4>
        <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
        <p><b>Tenant:</b></p>
        <p>{!! $data['agreement']['tnt_signature_'][1] !!}<br />{!! $data['agreement']['tenant_print_'][0] !!}<br />{!! $data['agreement']['tenant_phone_'][0] !!}<br />{!! $data['agreement']['tenant_email_'][0] !!}</p>
                
        <p><b>LANDLORD AGREES TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
        <p><b>Landlord: <span id="landlord_signature_2">{!! $data['agreement']['landlord_signature_2'] !!}</span></b></p>
        <p><b>By:</b></p>
        <p>{!! $data['agreement']['agent_signature_'][0] !!}</p>
        <h4><b>55.1. Complaint Procedure Addendum</b></h4>
        <p><u>Complaint Procedure.</u> Tenant hereby acknowledges and agrees that if Tenant shall have any complaint ("Complaint") related to, arising out of or connected with the condition of the apartment unit ("Unit Condition Matter", individually, and "Unit Condition Matters" in the aggregate), or anything else under the Lease, Tenant shall follow the procedure outlined herein prior to filing a lawsuit or any other proceeding against Landlord.  Unit Condition Matters shall include, without limitation, claims related to the habitability of the unit, defects in the unit, maintenance and repair related matters, bugs, roaches and similar creatures, etc.</p>
        <p><u>Procedure:</u></p>
        <table>
          <tr>
            <td width="5"></td>
            <td width="10">1.</td>
            <td width="535"><p>Tenant shall notify Landlord in writing of such Complaint describing in detail the Unit Condition Matter, and shall provide Landlord a reasonable opportunity to cure the Unit Condition Matter; </p></td>
          </tr>
          <tr>
            <td></td>
            <td>2.</td>
            <td><p>If Tenant is not satisfied with the Landlord’s response or cure to the Unit Condition Matter, then Tenant shall notify Landlord in writing of such dissatisfaction, and shall provide Landlord a further reasonable opportunity to cure the Unit Condition Matter; </p></td>
          </tr>
          <tr>
            <td></td>
            <td>3.</td>
            <td><p>If Tenant remains dissatisfied with Landlord’s cure of the Unit Condition Matter, Tenant shall notify  Landlord in writing, whereupon Landlord shall engage a third party inspector ("Inspector") to inspect the Unit Condition Matter and evaluate the remedy Landlord has implemented, and determine if further action is required by Landlord.  If the Inspector determines that Tenant is the cause of the Unit Condition Matter or that Landlord has adequately remedied the Unit Condition Matter, then Tenant shall reimburse Landlord for the cost of the Inspector.  If the Inspector determines that further action is required by Landlord, then Landlord shall pay the Inspector's cost and shall undertake the additional action recommended by the Inspector; and</p></td>
          </tr>
          <tr>
            <td></td>
            <td>4.</td>
            <td><p>Tenant shall fully cooperate with Landlord, its vendors and the Inspector, which shall include allowing Landlord and its vendors’ reasonable access to the apartment unit to inspect, take pictures, devise a solution to the Unit Condition Matter and implement a solution thereto, as well as access to follow up and check on the effectiveness of any solution undertaken.</p></td>
          </tr>
        </table>
        <p>If Tenant files a lawsuit or other action against Landlord without following the Complaint Procedure outlined above, Landlord’s liability to Tenant for all Unit Condition Matters shall not exceed $1,000 in the aggregate.</p>
        <p><u>Liability Cap.</u> Notwithstanding anything else that may be construed to the contrary herein, aggregate liability of Landlord and all Landlord Relates Parties under the Lease for all Unit Condition Matters shall be capped at an amount equal to four (4) times the monthly rental rate payable by the Tenant each month up to a maximum aggregate amount of $10,000.  Tenant acknowledges that Landlord has set the monthly rental rate for the apartment unit based on the liability cap in the immediately preceding sentence, and that Landlord is relying on such liability cap in entering into the [Lease/Addendum]. For the avoidance of doubt, each party shall bear its own attorney’s fees in any action or other proceeding related to, arising out of or connected with the Lease, including, without limitation, Unit Condition Matters. </p>
        <p><u>Written Notices:</u> All written notices to Landlord must be given to both the onsite manager of the apartment building with a copy being sent to the corporate headquarters of the management company and the Landlord at the following address: </p>
        <p>{!! $data['agreement']['notice_address_1'] !!}</p>
        <p>(Phone number is) {!! $data['agreement']['payment_phone_number_3'] !!} at (address) {!! $data['agreement']['rent_payment_address_3'] !!}</p>
        <p>Office hours: <u><b>9:00 AM</b></u> to <u><b>6:00 PM</b></u> on <u><b>Monday to Friday</b></u>. <u><b>10:00 AM</b></u> to <u><b>5:00 PM</b></u> on <u><b>Saturday</b></u> and </u><b>Sunday</b></u></p>
        <p>If there is no onsite manager, such written notice must instead be given to the local office of {!! $data['agreement']['notice_local_address_1'] !!} where tenant pays its rent, as well as to the corporate office.</p>
        <p>Additional Notification Address: (Optional)<br />{!! $data['agreement']['optional_address_1'] !!}</p>
        <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
        <p>{!! $data['agreement']['tnt_signature_'][2] !!}<br />{!! $data['agreement']['tenant_print_'][1] !!}</p>
        <h4><b>55.2. Service Procedure Addendum</b></h4>
        <p><u>Service Procedure.</u> Tenant hereby acknowledges and agrees that if Tenant shall have any issue ("Service Procedure") related to, arising out of or connected with the condition of the apartment unit  or the Premises or anything under the Lease (each a "Unit Condition Matter"), Tenant shall follow the procedure outlined herein prior to filing a lawsuit or any other proceeding against Landlord or any of the Landlord Related Parties.  Unit Condition Matters shall include, without limitation, claims related to the habitability of the unit, defects in the unit, maintenance and repair related matters, bugs, roaches and similar creatures, etc. All initially capitalized terms used in this Addendum without definition shall have the meaning ascribed to such terms in the Agreement to which this Addendum is attached.</p>
        <p><u>Procedure:</u></p>
        <table>
          <tr>
            <td width="5"></td>
            <td width="10">5.</td>
            <td width="500"><p>Tenant shall notify Landlord in writing of such Complaint describing in detail the Unit Condition Matter, and shall provide Landlord a reasonable opportunity to cure the Unit Condition Matter;</p></td>
          </tr>
          <tr>
            <td></td>
            <td>6.</td>
            <td><p>If Tenant is not satisfied with the Landlord’s response or cure to the Unit Condition Matter, then Tenant shall notify Landlord in writing of such dissatisfaction, and shall provide Landlord a further reasonable opportunity to cure the Unit Condition Matter; </p></td>
          </tr>
          <tr>
            <td></td>
            <td>7.</td>
            <td><p>If Tenant remains dissatisfied with Landlord’s cure of the Unit Condition Matter, Tenant shall notify  Landlord in writing, whereupon Landlord shall engage a third party inspector ("Inspector") to inspect the Unit Condition Matter and evaluate the remedy Landlord has implemented, and determine if further action is required by Landlord.  If the Inspector determines that Tenant is the cause of the Unit Condition Matter or that Landlord has adequately remedied the Unit Condition Matter, then Tenant shall reimburse Landlord for the cost of the Inspector.  If the Inspector determines that further action is required by Landlord, then Landlord shall pay the Inspector’s cost and shall undertake the additional action recommended by the Inspector; and</p></td>
          </tr>
          <tr>
            <td></td>
            <td>8.</td>
            <td><p>Tenant shall fully cooperate with Landlord, its vendors and the Inspector, which shall include allowing Landlord and its vendors’ reasonable access to the apartment unit to inspect, take pictures, devise a solution to the Unit Condition Matter and implement a solution thereto, as well as access to follow up and check on the effectiveness of any solution undertaken.</p></td>
          </tr>
        </table>
        <p><u>Consequence for Failing to Follow Procedure:</u> If Tenant files a lawsuit or other action against Landlord or any of the Landlord Related Parties without following the Service Procedure outlined above, then:</p>
        <table> 
            <tr><td width="5"></td><td width="10">i.</td><td width="500">the aggregate liability of Landlord and all Landlord Relates Parties to Tenant and all Occupants for all Unit Condition Matters shall not exceed $1,000 in the aggregate;</td></tr>
            <tr><td width="5"></td><td width="10">ii.</td><td>such failure shall be a material breach of the Lease, which, among other remedies provided for in the Lease, shall entitle the Landlord to terminate the Lease on 30 days’ notice to the Tenant or such longer period as may be required by applicable law; and</td></tr>
            <tr><td width="5"></td><td width="10">iii.</td><td>neither Tenant nor any Occupant shall be entitled to recover against any Landlord or any Landlord Related Parties or any other party (i) punitive damages, nor (ii) the fees, costs or other charges incurred by any attorney(s) representing Tenant, any Occupant or invitee of Tenant or Occupant in any such lawsuit or other legal proceeding, and Tenant hereby knowingly and intentionally waives any such rights to punitive damages and attorneys’ fees, whether by statute or otherwise.</td></tr>
        </table>
        <p>Tenant acknowledges and agrees that the lease termination remedy in clause (ii) above is a reasonable remedy for breach of the Lease by Tenant or any Occupant thereof and is not intended to constitute retaliation against Tenant or such Occupant for initiating legal action against Landlord or any of the Landlord Related Parties.</p>
        <p><u>Indemnity</u>:  Tenant hereby agrees to indemnify, defend (with counsel selected by the Landlord Related Parties) and hold Landlord and each of the Landlord Related Parties harmless from and against any and all claims, demands, losses, liabilities, causes of action, suits, judgments, damages, costs and expenses (including attorneys’ fees) arising from (i) the failure to follow the Service Procedure set forth in this addendum by Tenant or any Occupant or any of their respective agents, employees, contractors, shareholders, partners, invitees, guests, sub Tenants, assignees, etc.; and (ii) the breach of the Lease by Tenant or any Occupant or any of their respective agents, employees, contractors, shareholders, partners, invitees, guests, sub Tenants, assignees, etc. </p>
        <p><u>Liability Cap.</u> Notwithstanding anything else that may be construed to the contrary herein, the aggregate liability or Landlord and all Landlord’s Relates Parties under the Lease for all Unit Condition Matters shall be capped at an amount equal to four (4) times the monthly rental rate payable by the Tenant each month up to a maximum aggregate amount of $10,000.  Tenant acknowledges that Landlord has set the monthly rental rate for the apartment unit based on the liability cap in the immediately preceding sentence, and that Landlord is relying on such liability cap in entering into this Agreement.  For the avoidance of doubt, each party shall bear its own attorney’s fees related in any action or other proceeding related to, arising out of or connected with Unit Condition Matters.</p>
        <p><u>Written Notices:</u> All written notices to Landlord must be given to both the onsite manager of the apartment building with a copy being sent to the corporate headquarters of the management company and the Landlord at the following address: </p>
        <p>{!! $data['agreement']['notice_address_2'] !!}</p>
        <p>(Phone number is) {!! $data['agreement']['payment_phone_number_2'] !!} at (address) {!! $data['agreement']['rent_payment_address_2'] !!}</p>
        <p>Office hours: <u><b>9:00 AM</b></u> to <u><b>6:00 PM</b></u> on <u><b>Monday to Friday</b></u>. <u><b>10:00 AM</b></u> to <u><b>5:00 PM</b></u> on <u><b>Saturday</b></u> and </u><b>Sunday</b></u></p>
        <p>If there is no onsite manager, such written notice must instead be given to the local office of {!! $data['agreement']['notice_local_address_2'] !!} where tenant pays its rent, as well as to the corporate office.</p>
        <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
        <p>{!! $data['agreement']['tnt_signature_'][3] !!}<br />{!! $data['agreement']['tenant_print_'][2] !!}</p>
        <h4><b>55.3. Bed Bug Addendum</b></h4>
        <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: {!! $data['agreement']['full_prop_address_1'] !!}<br />Tenant and Landlord hereby enter into Bed Bug Addendum ("the Addendum") concurrently with and effective as of the date of the Agreement. This addendum sets forth the agreement of Tenant and Landlord for prevention of bed bugs at the Premises.</p>
         <ol style="padding-left:2.5%;">
            <li><p><b>Purpose:</b> This Addendum modifies the Agreement and addresses situations related to bed bugs, which may be discovered infesting the dwelling or personal property in the dwelling. You understand that we relied on your representations to us in this Addendum.</p></li>
            <li><p><b>Inspection:</b> You agree that you: (check one)<br>({!! $data['agreement']['inspection_prior_1'] !!}) have inspected the dwelling prior to move in and that you did not observe any evidence of bed bugs or bed bug infestation; or<br>({!! $data['agreement']['inspection_after_1'] !!}) will inspect the dwelling within 24 hours after move-in/renewal and notify Landlord of any bed bugs or bed bug infestation.</p></li>
            <li><p><b>Infestations:</b> You agree that you have read the information in this addendum about bed bugs and: (check one)<br>({!! $data['agreement']['no_infestation_1'] !!}) you are not aware of any infestation or presence of bed bugs in your current or previous dwellings or home. You agree that you are not aware of any bed bug infestation or presence in your furniture, clothing, personal property or possessions. You agree that you have not been subjected to conditions in which there was any bed bug infestation or presence, or<br>({!! $data['agreement']['previous_infestation_1'] !!}) you agree that if you previously lived anywhere that had a bed bug infestation that all of your personal property (including furniture, clothing and other belongings) has been treated by a licensed pest control professional. You agree that such items are free of further infestation. If you disclose a previous experience of bed bug infestation, we can review documentation of the treatment and inspect your personal property and possessions to confirm the absence of bed bugs. You agree that any previous bed bug infestation which you may have experienced is disclosed here:<br>{!! $data['agreement']['disclosed_infestations_1'] !!}</p></li>
            <li><p><b>Access for Inspection and Pest Treatment:</b> You must allow us and our pest control agents to access the dwelling at reasonable times to inspect for or treat bed bugs as allowed by law. You and your family members, occupants, guests, and invitees must cooperate and will not interfere with inspections or treatments. We have the right to select any licensed pest control professional to treat the dwelling and building. We can select the method of treating the dwelling, building and common areas for bed bugs. You are responsible for and must, at your own expense, have your own personal property, furniture, clothing and possessions treated according to accepted treatment methods established by a licensed pest control firm that we approve. You must do so as close as possible to the time we treated the dwelling. If you fail to do so, you will be in default of the Agreement and we will have the right to terminate your right of occupancy and exercise all rights and remedies under the Agreement. You agree not to treat the dwelling for a bed bug infestation on your own.</p></li>
            <li><p><b>Notification:</b> You must promptly notify us: (i) of any known or suspected bed bug infestation or presence in the dwelling, or in any of your clothing, furniture or personal property, (ii) or if you discover any condition or evidence that might indicate the presence or infestation of bed bugs, or any confirmation of bed bug presence by a licensed pest control professional or other authoritative source.</p></li>
            <li><p><b>Cooperation:</b> If we confirm the presence or infestation of bed bugs, you must cooperate and coordinate with us and our pest control agents to treat and eliminate the bed bugs. You must follow all directions from us or our agents to clean and treat the dwelling and building that are infested. You must remove or destroy personal property that cannot be treated or cleaned as close as possible to the time we treated the dwelling. Any items you remove from the dwelling must be disposed of off-site and not in the property's trash receptacles. If we confirm the presence or infestation of bed bugs in your dwelling, we have the right to require you to temporarily vacate the dwelling and remove all furniture, clothing and personal belongings in order for us to perform pest control services. If you fail to cooperate with us, you will be in default, and we will have the right to terminate your right of occupancy and exercise all rights and remedies under the Agreement.</p></li>
            <li><p><b>Responsibilities:</b> You may be required to pay all reasonable costs of cleaning and pest control treatments incurred by us to treat your dwelling for bed bugs. If we confirm the presence or infestation of bed bugs after you vacate your dwelling, you may be responsible for the cost of cleaning and pest control treatments. If you fail to pay us for any costs you are liable for, you will be in default, and we will have the right to terminate your right of occupancy and exercise all rights and remedies under the Agreement.</p></li>
            <li><p><b>Transfers:</b> If we allow you to transfer to another property because of the presence of bed bugs, you must have your personal property and possessions treated according to accepted treatment methods or procedures established by a licensed pest control professional. You must provide proof of such cleaning and treatment to our satisfaction.</p></li>
        </ol>
           <h4><b>Bed Bug Information</b></h4>
        <table>
          <tr>
              <td width="480">Bed bugs, with a typical lifespan of 6-12 months, are wingless, flat, broadly oval-shaped insects. Capable of reaching the size of an apple seed at full growth, bed bugs are distinguishable by their reddish-brown color, although after feeding on the blood of humans and warm-blooded animals -- their sole food source -- the bugs assume a distinctly blood-red hue until digestion is complete.<br><b>Bed Bugs Don't Discriminate</b><br>Bed bugs increased presence across the United States in recent decades can be attributed largely to a surge in international travel and trade. It's no surprise then that bed bugs have been found time and time again to have taken up residence in some of the fanciest hotels and apartment buildings in some of the nation's most expensive neighborhoods.<br>Nonetheless, false claims that associate bed bugs presence with poor hygiene and uncleanliness have caused rental housing Tenants, out of shame, to avoid notifying owners of their presence. This serves only to enable the spread of bed bugs.While bed bugs are, by their very nature, more attracted to clutter, they're certainly not discouraged by cleanliness.Bottom Line: bed bugs know no social and economic bounds; claims to the contrary are false.<br><b>Bed Bugs Don’t Transmit Disease</b><br>There exists no scientific evidence that bed bugs carry disease. In fact, federal agencies tasked with addressing pest of public health concern, namely the U.S. Environmental Protection Agency and the Centers for Disease Control and Prevention, have refused to elevate bed bugs to the threat level posed by disease carrying pests. Again, claims associating bed bugs with disease are false.<br><b>Identifying Bed Bugs</b><br>Bed bugs can often be found in, around and between:</td>
          </tr>
        </table>
        <table>
            <tr>
                <td width="1"></td>
                <td width="550">
                    <ul class="left-padding-p-2">
                       <li>Bedding</li>
                       <li>Bed Frames</li>
                       <li>Mattress Seams</li>
                       <li>Upholstered Furniture</li>
                       <li>Around, behind and under wood furniture, especially along areas where drawers slide.</li>
                       <li>Ceiling and wall junctions</li>
                       <li>Crown moldings</li>
                       <li>Behind and around wall hangings and loose wallpaper</li>
                       <li>Between carpeting and walls (carpet can be pulled away from the wall and tack strip)</li>
                       <li>Cracks and crevices in walls and floors</li>
                       <li>Inside electronic devices, such as smoke and carbon monoxide detectors.</li>
                       <li>Because bed bugs leave some persons with itchy welts strikingly similar to those caused by fleas and mosquitoes, the origination of such markings often go misdiagnosed. However, welts caused by bed bugs often times appear in succession and on exposed areas of skin, such as the face, neck and arms. In some cases, an individual may not experience any visible reaction from direct contact with bed bugs.</li>
                       <li>While bed bugs typically prefer to act at night, they often do not succeed in returning to their hiding spots without leaving traces of their presence through fecal markings of a red to dark brown color, visible on or near beds. Blood stains tend also to appear when the bugs have been squashed, usually by an unsuspecting host in their sleep. And, because they shed, it's not uncommon for skin casts to be left behind in areas frequented by bed bugs.</li>
                     </ul>
                </td>
            </tr>
          </table>
          <table>
            <tr>
              <td width="525"><b>Preventing Bed Bug Encounters When Traveling</b><br>Humans serve as bed bugs' main mode of transportation, it is extremely important to be mindful of bed bugs when away from home. Experts agree that the spread of bed bugs across all regions of the United States is largely attributed to an increase in international travel and trade. Travelers are therefore encouraged to take a few minutes upon arriving to their temporary destination to thoroughly inspect their accommodations, so as to ensure that any uninvited guests are detected before the decision is made to unpack. Bed bugs can easily travel from one room to another, it is also recommended that travelers thoroughly inspect their luggage and belongings for bed bugs before departing for home.<br><b>Bed Bug Do’s & Don’ts</b>
                <ol type="a">
                  <li><p>Do not bring used furniture from unknown sources into your dwelling. Countless bed bug infestations have stemmed directly from the introduction into a Tenant's unit of second-hand and abandoned furniture. Unless the determination can be made with absolute certainty that a piece of second-hand furniture is bed bug-free, Tenants should assume that the reason a seemingly nice looking leather couch, for example, is sitting curbside, waiting to be hauled off to the landfill, may very well be due to the fact that it's teeming with bed bugs.</p></li>
                  <li><p>Do address bed bug sightings immediately. Tenants who suspect the presence of bed bugs in their property must immediately notify the Landlord. Do not attempt to treat bed bug infestations. Under no circumstances should you attempt to eradicate bed bugs. Health hazards associated with the misapplication of traditional and nontraditional, chemical based insecticides and pesticides poses too great a risk to you and your neighbors.</p></li>
                  <li><p>Do comply with eradication protocol. If the determination is made that your property is indeed playing host to bed bugs, you must comply with the bed bug eradication protocol set forth by both the Landlord and their designated pest management company.</p></li>
                </ol>
              </td>
            </tr>
          </table>
          <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
          <p>{!! $data['agreement']['tnt_signature_'][4] !!}<br />{!! $data['agreement']['tenant_print_'][3] !!}</p>
          <p><b>Landlord: </b><span>{!! $data['agreement']['landlord_signature_3'] !!}</span></p>
          <p><b>By:</b>{!! $data['agreement']['agent_signature_'][1] !!}</p>
          <h4><b>55.4. Rental Mold and Ventilation Addendum</b></h4>
          <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
          <p>{!! $data['agreement']['full_prop_address_2'] !!}</p>
          <p><b>AND AGREEMENT:</b> Except as may be noted at the time of Tenant's move in inspection, Tenant agrees that the Premises is being delivered free of known damp or wet building materials ("mold") or mildew contamination. (If checked, the Premises was previously treated for elevated levels of mold that were detected.) Tenant acknowledges and agrees that (i) mold can grow if the Premises is not properly maintained; (ii) moisture may accumulate inside the Premises if it is not regularly aired out, especially in coastal communities; (iii) if moisture is allowed to accumulate, it can lead to the growth of mold, and (iv) mold may grow even in a small amount of moisture. Tenant further acknowledges and agrees that Tenant has a responsibility to maintain the Premises in order to inhibit mold growth and that Tenant's agreement to do so is part of Tenant's material consideration in Landlord's agreement to rent the Premises to Tenant. Accordingly, Tenant agrees to:</p>
          <table>
            <tr>
              <td width="15"></td>
              <td width="20"><b>1.</b></td>
              <td width="480"><p>Maintain the Premises free of dirt, debris and moisture that can harbor mold;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>2.</b></td>
              <td><p>Clean any mildew or mold that appears with an appropriate cleaner designed to kill mold;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>3.</b></td>
              <td><p>Clean and dry any visible moisture on windows, walls and other surfaces, including personal property as quickly as possible;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>4.</b></td>
              <td><p>Use reasonable care to close all windows and other openings in the Premises to prevent water from entering the Premises;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>5.</b></td>
              <td><p>Use exhaust fans, if any, in the bathroom(s) and kitchen while using those facilities and notify Landlord of any inoperative exhaust fans;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>6.</b></td>
              <td><p>Immediately notify Landlord of any water intrusion, including but not limited to, roof or plumbing leaks, drips or "sweating pipes";</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>7.</b></td>
              <td><p>Immediately notify Landlord of overflows from bathroom, kitchen or laundry facilities;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>8.</b></td>
              <td><p>Immediately notify Landlord of any significant mold growth on surfaces in the Premises;</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>9.</b></td>
              <td><p>Allow Landlord, with appropriate notice, to enter the Premises to make inspections regarding mold and ventilation; and</p></td>
            </tr>
            <tr>
              <td></td>
              <td><b>10.</b></td>
              <td><p>Release, indemnify, hold harmless and forever discharge Landlord and Landlord's employees, agents, successors and assigns from any and all claims, liabilities or causes of action of any kind that Tenant, members of Tenant's household or Tenant's guests or invitees may have at any time against Landlord or Landlord's agents resulting from the presence of mold due to Tenant's failure to comply with this Lease/Rental Mold and Ventilation Addendum.</p></td>
            </tr>
          </table>
          <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
          <p>{!! $data['agreement']['tnt_signature_'][5] !!}<br />{!! $data['agreement']['tenant_print_'][4] !!}</p>
          <p><b>LANDLORD AGREES TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS</b></p>
          <p><b>Landlord: <span>{!! $data['agreement']['landlord_signature_4'] !!}</span></b></p>
          <p><b>By</b></p>
          <p><b></b>{!! $data['agreement']['agent_signature_'][2] !!}</p>
          <h4><b>55.5. Addendum to Rental Agreement Apartment / House Rules</b></h4>
          <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
          <p>{!! $data['agreement']['full_prop_address_3'] !!}</p>
          <p>This addendum to the Rental Agreement replaces, in its entirety, any previous addendums or Apartment House Rules and any prior House Rules have no further force of effect.</p>
          <table>
            <tr>
              <td width="5"></td>
              <td width="15"><p><b>A.</b></p></td>
              <td width="15"><p></p></td>
              <td width="525"><p><b>GENERAL</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>1.</p></td>
              <td><p>This Addendum is incorporated by reference into the Rental Agreement between Landlord and Tenant.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>2.</p></td>
              <td><p>New rules and regulations or amendments to these rules may be adopted by Landlord upon giving 30 days’ notice in writing. These rules and any changes or amendments have a legitimate purpose and are not intended to be arbitrary or work as a substantial modification of tenant rights. They will not be unequally enforced. Tenant is responsible for their guests, and the adherence to these rules and regulations at all times, by all occupants or guests.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>3.</p></td>
              <td><p>In consideration of others, no Tenant shall make or emit any disturbing noises in the building by himself, his family or guests: nor do or permit anything by such persons that will interfere with the rights, comforts or convenience of other Tenants. Loud singing, playing on musical instruments or loud operation of a T.V., sound systems or recorder is not permitted. No loitering, visiting or loud talking is allowed outside the tenant's unit.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>4.</p></td>
              <td><p>In the event tenant or guest is inebriated; threatens, molests or disturbs another tenant or harasses manager; a three-day notice to quit will be served to tenant.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>5.</p></td>
              <td><p>Deposit and Rent Receipts: It is Tenant(s) responsibility to obtain receipt(s) for any payments made for the occupied premises. Tenants will not be discharged from their obligation without proof of payment.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>6.</p></td>
              <td><p>Tenant has inspected the premises, furnishings and equipment, and has found them to be satisfactory. All plumbing, heating and electrical systems are operative and deemed satisfactory .All deficiencies must be reported to Landlord in writing within 10 days from date of occupancy.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>7.</p></td>
              <td><p>All conduct in common area that unreasonably disturbs the quiet enjoyment of other tenants is prohibited.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>8.</p></td>
              <td><p>PETS AND WATER FILLED FURNITURE - Tenant agrees that he/she will not, without Landlord's expressed consent in writing endorsed hereon, bring upon, keep, maintain or permit to be kept or maintained, in, on, or upon the premises any dog, cat, bird, or other animal pet. Tenant agrees that he/she will not, without Landlord's expressed  consent In writing endorsed hereon, bring upon, keep, maintain or permit to be kept or maintained, in, on, or upon the premises any waterbeds, or liquid-filled furniture as provided under California <u>Civil Code</u> 1940.5</p></td>
            </tr>
        </table>
                <table>
            <tr>
              <td width="5"></td>
              <td width="15"><p><b>B.</b></p></td>
              <td width="15"><p></p></td>
              <td width="525"><p><b>NOISE AND CONDUCT</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>1.</p></td>
              <td><p>Tenants shall not allow any disturbing noises in the unit by Tenant, family or guests, nor permit anything by such persons which will interfere with the rights, comforts or conveniences of other persons.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>2.</p></td>
              <td><p>Tenant(s) is responsible for the activities and conduct of Tenant and their quests, outside of the unit, on the common grounds, parking areas, or use of recreation facilities must be reasonable at all times and not annoy or disturb other persons.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>3.</p></td>
              <td><p>Alcoholic beverages may not be consumed by Tenants or their guests anywhere on the property other than inside a consenting Tenant's apartment.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p><b>C.</b></p></td>
              <td><p></p></td>
              <td><p><b>CLEANLINESS AND TRASH</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>1.</p></td>
              <td><p>Landscaped portions of the property are intended to enhance the appearance of the property for all tenants. Tenants, occupants and guests are prohibited from any activity that threatens to or actually damages the landscaped features of the property including lawns, shrubs, flowerbeds or garden areas.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>2.</p></td>
              <td><p>Tenants shall assist Landlord in keeping the outside and common areas clean.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>3.</p></td>
              <td><p>Tenant shall not permit the littering of papers, cigarette butts or trash in and around the unit.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>4.</p></td>
              <td><p>Tenant shall ensure that no trash or other materials are accumulated which will cause a hazard or be in violation of any heath, fire or safety ordinance or regulation. The unit must be kept clean, sanitary and free from objectionable odors at all times.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>5.</p></td>
              <td><p>Tenant shall ensure that trash is placed inside the containers provided, and lids should not be slammed. Trash should not be allowed to accumulate and should be placed in the outside containers on a daily basis. Items too large to fit in the trash containers should be placed adjacent to the containers. Tenant shall not dispose of any combustible or hazardous material in the trash containers or bins. Such items will be deemed to be a nuisance and most be disposed of properly by the Tenant in accordance with State and local laws.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>6.</p></td>
              <td><p>All furniture must be kept inside the unit. Unsightly items must be kept out of sight.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>7.</p></td>
              <td><p>Tenant may not leave items in the hallways or other common areas.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>8.</p></td>
              <td><p>Clothing, curtains, rugs, etc., shall not be shaken or hung outside any window, ledge, or balcony.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>9.</p></td>
              <td><p>No car washing or car repairs are allowed at the property.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>10.</p></td>
              <td><p>Apartments with patios or balconies: Tenant must water and maintain shrubs and keep patios free of weeds. Landlord may inspect patios periodically. Neglect of the plants, shrubbery and trees will be considered damage to the premises. Barbecuing is permitted only on enclosed patios or the designated space where there are no patios. No outside storage is allowed in any patio or balcony. Common walk ways must always be kept clear.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p><b>D.</b></p></td>
              <td><p></p></td>
              <td><p><b>SAFETY</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>1.</p></td>
              <td><p>Security is the responsibility of each Tenant. Landlord assumes no responsibility or liability, unless otherwise provided by law, for Tenants' and guests' safety, or injury or damage caused by the acts of other persons. Landlord does not provide private protection services for Tenants.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>2.</p></td>
              <td><p>Tenant shall insure that all doors and windows are locked during Tenant's absence.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>3.</p></td>
              <td><p>Tenant shall insure that appliances be turned off before leaving the unit.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>4.</p></td>
              <td><p>When leaving for an extended period (over 14 days), Tenant shall notify Landlord how long Tenant will be away. Prior to any planned absence, Tenant shall give Landlord authority to enter the unit and provide Landlord with the name of any per­ son or entity permitted by Tenant to enter unit.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>5.</p></td>
              <td><p>Smoking in bed is prohibited.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>6.</p></td>
              <td><p>The use or storage of gasoline, cleaning solvent or other combustibles in the unit is prohibited.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>7.</p></td>
              <td><p>The use of charcoal barbecues is prohibited unless consent is obtained from the Landlord.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>8.</p></td>
              <td><p>Tenant shall insure that no personal belongings, including bicycles, play equipment or other items are left in the halls, balconies, and stairways or about the building unattended.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p><b>E.</b></p></td>
              <td><p></p></td>
              <td><p><b>MAINTENANCE, REPAIRS AND ALTERATIONS</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>1.</p></td>
              <td><p>Tenant shall advise Landlord, in writing, of any items requiring repair (dripping faucets, light switches, etc.). Notification should be immediate in an emergency or for normal problems within business hours. Repair request should be made as soon as the defect is noted.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>2.</p></td>
              <td><p>All service requests be made to Resident Manager and/or Property Management Company in writing. No service request be made directly to maintenance person.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>3.</p></td>
              <td><p>No alterations or improvements shall be made by Tenant without the consent of Landlord. Any article attached to the woodwork, walls, floors or ceilings shall be the sole responsibility of the Tenant. Tenant shall be liable for any repairs necessary during or after residency to restore premises to the original condition. Glue or tape shall not be used to affix pictures or decorations.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>4.</p></td>
              <td><p>Costs to be paid by Tenant(s) due to their negligence: Tenant(s) will be charged for the costs incurred by the Landlord due to Tenant(s) carelessness and/or negligence. Some of the examples are broken windows, damaged screens, clogged drains, missing smoke detectors, scratches on the floors, bleach or oil spots on the carpet, missing light fixture covers, etc. Tenant(s) must pay upon demand. In the event Tenant(s) fail to pay, the Landlord and/or management may reduce the security deposit for that amount or file small claim action in court.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>5.</p></td>
              <td><p>Stoves & garbage disposals in working order are the Tenant's responsibility to maintain and keep clean.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>6.</p></td>
              <td><p>Nothing herein contained shall be construed to grant Tenant any right to enter upon any portion of the roof of said premises for any purpose whatsoever without Landlord's prior consent in writing. TENANT SHALL NOT ENTER INTO A CONTRACTUAL AGREEMENT   WITH A   CABLE T.V.  COMPANY OR SATELLITE DISH. If Tenant desires to have a satellite dish, a separate addendum must be executed.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p><b>F.</b></p></td>
              <td><p></p></td>
              <td><p><b>PARKING AND SPACES</b></p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>1.</p></td>
              <td><p>Inoperable, abandoned, unregistered vehicles or vehicles leaking fluids are subject to being towed pursuant to California Vehicle Code section 22658.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>2.</p></td>
              <td><p>All vehicles on the premises must be operational, currently registered and displaying current registration tags on the rear license plate, insured and free from leaking fluids. There shall be no vehicle repairs or maintenance performed on or about the premises.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>3.</p></td>
              <td><p>No Tenant shall keep, maintain or allow to remain on the premises for a period in excess of seven (7) days, any non- working, inoperable or non-functioning vehicle of any kind. The parties agree that the presence of any such vehicle on the premises for a period in excess of seven (7) days shall constitute a nuisance within the provisions of California <u>Civil Code</u>. Section 3479 and may, at Landlord's option, be the basis for terminating the tenancy herein.</p></td>
            </tr>
            <tr>
              <td></td>
              <td><p></p></td>
              <td><p>4.</p></td>
              <td><p>This notice affects all Tenants and their visitors. Licensed towing has been hired to enforce all parking regulations and to tow and impound any vehicles in violation that are parked on this property. Towing company will tow all vehicles that are: Parked in Driveways, Alleyways, Red Zones - Fire Lanes, Inoperative Vehicles, and Vehicles without Current Registration and Vehicles Backed into Carports. Use your assigned parking stall. If you do not have an assigned parking stall, see the manager. All visitors must park on the street. THERE WILL BE NO FURTHER NOTICES OR EXCEPTIONS AFTER THREE-DAY NOTICE IS GIVEN!! VIOLATORS WILL BE TOWED AWAY WITHOUT FURTHER WARNING. UNDER THE STATE LAW, VEHICLE(S) WITHOUT CURRENT REGISTRATION ARE CONSIDERED INOPERABLE.</p></td>
            </tr>
          </table>
          <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
          <p><b>Tenant:</b></p>
          <p>{!! $data['agreement']['tnt_signature_'][6] !!}<br />{!! $data['agreement']['tenant_print_'][5] !!}</p>
          <h4><b>55.6. Move In / Move Out Inspection</b></h4>
          <p><b>Property Address</b> {!! $data['agreement']['prop_address_1'] !!} Unit No.{!! $data['agreement']['unit_no_2'] !!}<br />Inspection: Move In (Date) {!! $data['agreement']['move_in_date_1'] !!} Move out (Date) {!! $data['agreement']['move_out_date_1'] !!}<br />Tenant(s): </p>
          <p>{!! $data['agreement']['tenant_lists_1'] !!}</p>
          <p>Completing this Form, check the premises carefully and be specific in all items noted. Check the appropriate letter:</p>
        <table>
            <tr>
                <td style="width: 16%;"><b><u>SECTION</u></b></td>
                <td style="width: 63%;"><b><u>MOVE IN</u></b></td>
                <td style="width: 21%;"><b><u>MOVE OUT</u></b></td>
            </tr>
        </table>
        <table>
            <tr>
                <td style="width: 16%;"></td>
                <td style="width: 62%;">
                    <table>
                        <tr>
                            <td style="width: 9%;">N</td>
                            <td style="width: 9%;">S</td>
                            <td style="width: 9%;">O</td>
                            <td style="width: 33%;"></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 37%;">
                    <table>
                        <tr>
                            <td style="width: 16%;">S</td>
                            <td style="width: 18%;">O</td>
                            <td style="width: 16%;">D</td>
                            <td style="width: 3%;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>{!! $data['inspectionResponse'] !!}<b>Other</b><p>{!! $data['agreement']['other_notes_1'] !!}</p>
        <h5><b>Move In Inspection</b></h5>
          <table class="table-form tnt-form">
            <tr>
              <td width="50%"><p><b>Tenant’s Initial(s) <span id="tnt_initial_10">{!! $data['agreement']['tnt_initial_10'] ? $data['agreement']['tnt_initial_10'] : '' !!}</span></b></p></td>
              <td width="50%"><p><b>Landlord’s/Authorized Agent’s Initials:</b><span id="landlord_initial_2">{!! $data['agreement']['landlord_initial_2'] ? $data['agreement']['landlord_initial_2'] : '' !!}</span></p></td>
            </tr>
          </table>
        <h5><b>Move Out Inspection</b></h5>
        <table class="table-form tnt-form">
            <tr>
              <td width="50%"><p><b>Tenant’s Initial(s) <span id="tnt_initial_11">{!! $data['agreement']['tnt_initial_11'] ? $data['agreement']['tnt_initial_11'] : '' !!}</span></b></p></td>
              <td width="50%"><p><b>Landlord’s/Authorized Agent’s Initials:</b><span id="landlord_initial_3">{!! $data['agreement']['landlord_initial_3'] ? $data['agreement']['landlord_initial_3'] : '' !!}</span></p></td>
            </tr>
          </table>
          <h4><b>55.7. Disclosure of Information on Lead-Based Paint and/or Lead-Based Paint Hazards</b></h4>
          <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: </p>
          <p>{!! $data['agreement']['full_prop_address_4'] !!}</p>
          <p><b>Lead Warning Statement</b><br>Housing built before 1978 may contain lead-based paint. Lead from paint, paint chips, and dust can pose health hazards if not managed properly. Lead exposure is especially harmful to young children and pregnant women. Before renting pre-1978 housing, Landlords must disclose the presence of known lead-based paint and/or lead-based paint hazards in the dwelling. Tenants must also receive a federally approved pamphlet on lead poisoning prevention.<br><b>Landlord’s Disclosure</b><br />Presence of lead-based paint and/or lead-based paint hazards (check (i) or (ii) below):<br>(i) {!! $data['agreement']['known_lead_paint_1'] !!} Known lead-based paint and/or lead-based paint hazards are present in the housing (explain).<br>(ii){!! $data['agreement']['not_known_lead_paint_1'] !!} Landlord has no knowledge of lead-based paint and/or lead-based paint hazards in the housing.<br>Records and reports available to the Landlord (check (i) or (ii) below):<br />(i) {!! $data['agreement']['has_paint_record_1'] !!} Landlord has provided the Tenant with all available records and reports pertaining to lead-based paint and/or lead-based paint hazards in the housing (list documents below).<br>(ii){!! $data['agreement']['no_paint_record_1'] !!} Landlord has no reports or records pertaining to lead-based paint and/or lead-based paint hazards in the housing.<br><b>Tenant’s Acknowledgment (initial)</b><br>Tenant has received copies of all information listed above.<br>Tenant has received the pamphlet Protect Your Family from Lead in Your Home (attached to this addendum).</p>
          <table>
            <tr>
                <td style="white-space: nowrap;" width="480"><p><b>Tenant's Initial(s) <span id="tnt_initial_12">{!! $data['agreement']['tnt_initial_12'] ? $data['agreement']['tnt_initial_12'] : '' !!}</span></b></p></td>
            </tr>
          </table>
          <p><b>Certification of Accuracy</b><br>The Following parties have reviewed the information above and certify, to the best of their knowledge, that the information they have provided is true and accurate.</p>
          <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
          <p><b>Tenant:</b></p>
          <p>{!! $data['agreement']['tnt_signature_'][7] !!}<br />{!! $data['agreement']['tenant_print_'][6] !!}</p>
          <p><b>LANDLORD AGREES TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
          <p><b>Landlord: <span>{!! $data['agreement']['landlord_signature_6'] !!}</span></b></p>
          <p><b>By:</b></p>
          <p><b></b>{!! $data['agreement']['agent_signature_'][4] !!}</p>
          {!! $data['images'] !!}
          <h4><b>55.8. Cleaning & Maintenance Guidelines</b></h4>
          <p><b>Dear Tenant,</b><br>It is our goal to provide a well maintained property at the beginning of the lease term and to work with you to maintain the property in good condition at all times during the Agreement. At the time you take possession of the property, it should be in a clean, well-maintained condition with all appliances and mechanical systems functioning correctly. Any pre-existing conditions and/or damages should be noted clearly on your <b><u>walk through inspection sheet</u></b>. If any conditions or damages are found that were not readily visible during the initial inspection they should be reported immediately.<br>The enclosed information outlines the PAMA Management policy regarding Tenant cleaning & maintenance responsibilities during the Agreement and, if followed, will help you receive a maximum security deposit refund at move out. This guide is intended as an outline of maintenance responsibility only and cannot address every possible cleaning or maintenance issue.</p>
          <p><b>Smoke Detectors/ Carbon Monoxide Detectors</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Keep smoke/carbon monoxide detectors free from any dust or obstructions and change detector batteries every 6 months. We recommend changing batteries when daylight savings time occurs.</li>
                          <li>Do not remove or disconnect smoke detectors.</li>
                          <li>Report any non-functioning smoke detectors immediately.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Plumbing</b></p>
          <table>
              <tr>
                  <td width="525">
                    <ul class="left-padding-p">
                        <li>Clogged plumbing due to day-to-day waste removal is a Tenant responsibility unless it occurs within the first 30 days of the Agreement and is not a result of the current Tenant’s use or abuse or it is determined that the clog is due to pre-existing conditions.</li>
                        <li>Toilet flush handles, flappers and chains are a Tenant maintenance responsibility unless they fail within the first 30 days of the Agreement.</li>
                        <li>Be careful to ensure that toys or hard objects are not flushed in the toilet as they can become lodged inside the toilet. In some cases the toilet may need to be replaced and the cost to repair or replace the toilet is a Tenant responsibility.</li>
                        <li>Leaky faucets and/or plumbing pipes should be reported immediately. They are considered normal wear and tear and are a landlord responsibility to maintain unless it is observed that they are caused by Tenant misuse. In the event that a plumbing leak goes unreported and causes excessive damage to the property, the Tenant will be held liable for the additional damage.</li>
                    </ul>
                  </td>
              </tr>
          </table>
          <p><b>Appliances - Range & Vent Hood</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Stove Burner drip pans are a Tenant responsibility to maintain. The drip pans should be new or in excellent condition at the beginning of your Agreement and should be in the same condition at the end of your Agreement. If drip pans are in poor condition at move out we will deduct from your security deposit.</li>
                          <li>Coil stove burners are relatively inexpensive and simple to replace. They eventually fail due to day-to-day use, and similar to light bulbs, are a Tenant responsibility to replace unless they fail within the first 30 days of the Agreement.</li>
                          <li>Flat surface stoves should be cleaned with only approved cleaning agents. Tenant will be held liable for any damage to the stove surface caused by abrasive cleaners and/or abuse.</li>
                          <li>Range should be pulled away from wall and cleaned behind every 6 months. Take this opportunity to clean any food or grease that may have accumulated on the sides of the range, wall and cabinets.</li>
                          <li>Oven should be cleaned at minimum every 6 months.</li>
                          <li>The Grease Filter, located in the vent hood or built in microwave, is a Tenant responsibility to maintain. The Grease Filter should be in good clean condition at the beginning of the lease. It can be removed and hand washed in warm soapy water or it can be placed in the dishwasher on a regular basis as needed to maintain it. If you’re cooking habits involve excess oil or grease we highly recommend washing the filter on a monthly basis to minimize the possibility of a grease fire.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Appliances – Refrigerator</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Refrigerator coils are located either behind or under the refrigerator. Tenants should clean behind and under the refrigerator as part of their regular home cleaning schedule. Dirty refrigerator coils will cause your refrigerator to work harder to stay cool and thus increase your electric bill.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Appliances – Dishwasher</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Report any water coming from under the dishwasher or around the door immediately.</li>
                          <li>Whenever you empty the dishwasher, look for paper, glass or debris that may collect at the bottom near the filter. Remove any debris immediately as it can cause damage to the internal components of the pump and drain system.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Appliances - Garbage Disposal</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Garbage disposal is used to grind and dispose of soft foods only. Do not allow utensils, hard foods or non- organic debris to enter the garbage disposal. (Eggshells, coffee grounds or rice should NEVER go into the garbage disposal)</li>
                          <li>If the disposal makes a humming noise, but does not function, there is debris lodged inside. Turn off electrical breaker, remove debris if possible, turn on breaker and test disposal.</li>
                          <li>If disposal neither functions nor makes a humming noise, check breaker in breaker box and check reset button at bottom of disposal</li>
                          <li>Report any leaks coming from garbage disposal immediately.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Heating Ventilation and Air Conditioning</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>We cannot resolve failing A/C systems unless we know about them. Report any unusual noises or smells coming from the air conditioner and any moisture in/around the A/C closet immediately.</li>
                          <li>It is imperative that Air Conditioning filters be changed on a regular basis. We recommend that the filter(s) be changed monthly. Tenant will be held liable for any damages that are a result of the air filter(s) not being maintained during the tenancy. Filter(s) may be located inside the Air Handler or inside the return air vent. If you are unsure of your Air Conditioning filter’s size or location, inquire with your property Landlord.</li>
                          <li>Whenever the lawn is cut, dirt, dust and grass clippings are thrown into the air and can be sucked into the outside air-condensing unit if it is running. This will clog the unit and reduce its efficiency overtime. It is recommended that the air conditioner be off whenever the grass is being cut or trimmed nearby the outside air-condensing unit.</li>
                          <li>Beware of wires and pipes behind or around outside air condensing unit. Be careful not to damage the A/C control wires whenever trimming grass nearby the unit and make sure the water condensation pipe stays above the ground level and keep it free of dirt and debris.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Carpets</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Please remember that normal wear and tear is expected in your property, this includes carpeted areas. Excessive wear and tear will happen if carpets are not cleaned properly on a regular basis. We recommend the following as a guide to caring for your carpets. You can reduce soil build up in carpeting by removing shoes when entering the home and by avoiding walking on carpet in bare feet as body oils will be transferred to the carpet, which makes it harder to remove soil by way of vacuuming.</li>
                          <li>Vacuum carpets on a weekly basis paying special attention to "high traffic" areas as ground in sand and dirt will damage carpet fibers. We recommend that inexpensive throw rugs be used in "high traffic" areas to reduce excessive damage to carpets.</li>
                          <li>Clean up spills as fast as you can. Blot or scrape up as much of the spill as possible, blotting with a clean dry towel from the outside toward the center. If you use a stain remover, test it first on an inconspicuous area of the carpet to make sure it does not damage the carpet.</li>
                          <li>There are many tricks to removing set in stains, gum, candle wax etc. If you have any questions consult a professional or contact our office for suggestions.</li>
                          <li>It is recommended that carpets be professionally cleaned as needed or at least once per year. If you rent or use your own carpet cleaner, make sure to follow the manufacturer’s instructions so that damage to carpets does not occur. Using excess soap or water can damage carpets and the underlying padding.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Flooring</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>All flooring should be cleaned on a regular basis to ensure the property is kept in clean and sanitary condition according to the Agreement.</li>
                          <li>Ceramic and vinyl flooring should be swept and mopped on a weekly basis paying special attention to high traffic areas, edges and corners where a mop doesn’t easily clean.</li>
                          <li>Wood and laminate flooring should be cleaned on a weekly basis. Do not use excessive water on wood or laminate floors as this can damage the flooring. If mopping, use only a slightly damp mop.</li>
                          <li>Clean and dry any spills immediately.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          <p><b>Walls, Blinds & Fixtures</b></p>
          <table>
              <tr>
                  <td width="525">
                      <ul class="left-padding-p">
                          <li>Dust blinds no less than once a month, wiping any stains as needed. In areas where grease build up may be a concern (such as kitchen areas) wipe blinds with a suitable cleaner.</li>
                          <li>Wipe any handprints or stains on walls, doors and switch plates as needed or at minimum every 3 months.</li>
                          <li>Dust or vacuum baseboards, door trim, light fixtures and ceiling fan blades as needed or at minimum every 3 months.</li>
                          <li>Remove light fixture glass covers and wash with warm soapy water or in dishwasher as needed or at minimum once per year.</li>
                      </ul>
                  </td>
              </tr>
          </table>
          
  <p><b>Emergency maintenance items involving plumbing leaks where moisture is found in carpets walls or under sinks; function of the heating system, and hot water heater should be reported immediately to our office by phone.</b><br>Non-emergency maintenance items should be reported to our office in writing via email, postal service, or hand carried.<br>Please help us ensure that the property you reside in stays well maintained for your and the Landlord’s benefit for years to come by following the above procedures</p>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][8] !!}<br />{!! $data['agreement']['tenant_print_'][7] !!}</p>
  <h4><b>55.9 Notice of Periodic Application of Pesticides by Pest Control Operator Addendum</b></h4>
  <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
  <p>{!! $data['agreement']['full_prop_address_5'] !!}</p>
  <p>California law requires that an Owner/Agent of a residential dwelling unit provide each new tenant a copy of the notice provided by a registered pest control company if a contract for periodic pest control service has been executed.</p>
  <p>The premises you are renting, or the common areas of the building are covered by such a contract for regular pest control service, so you are being notified pursuant to the law. The notice provided by the pest control company is attached to this Acknowledgment.</p>
  <p>{!! $data['agreement']['tnt_signature_'][9] !!}<br />{!! $data['agreement']['tenant_print_'][8] !!}</p>
  <h4><b>55.10. Water Conservation Addendum</b></h4>
  <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
  <p>{!! $data['agreement']['full_prop_address_6'] !!}</p>
  <p>The governor on January 17, 2014 proclaimed a State of Emergency due to record dry conditions in the State of California. On April 1, 2015, the governor issued Executive Order B-29-15, which ordered the State Water Resources Control Board to impose restrictions to achieve a 25 percent reduction in potable urban water usage. Local water agencies across California are taking action in the face of record-dry conditions. Many water suppliers are implementing mandatory restrictions on water use and stepping up conservation outreach to help their customers reduce water use and protect water supply reserves. In addition, the State Water Resources Control Board has approved an emergency regulation under which all Californians will be expected to stop: washing down driveways and sidewalks; watering of outdoor landscapes that cause excess runoff; using a hose to wash a motor vehicle, unless the hose is fitted with a shut-off nozzle; and using potable water in a fountain or decorative water feature, unless the water is recirculated.</p>
  <p>Links to local information and contacts is available at <b><u>http://droughtresponse.acwa.com/agencies</u></b></p>
  <ol>
      <li>Resident shall take all steps necessary to ensure that he/she is aware of water use restrictions. Most water agencies have toll-free numbers, email alerts and/or websites that provide this information.</li>
      <li>Resident shall comply with all <u>state and local</u> water use restrictions. Restrictions can vary from one area to another. Resident is responsible for obtaining information about the restrictions specific to the City or County in which the premises are located.</li>
      <li>Resident remains responsible for maintaining landscaping, including sufficient <u>watering, consistent with state and local water use restrictions</u>, if required to do so by the Rental/Lease Agreement. Please contact Owner/Agent for more information.</li>
      <li>Resident is responsible for any fines or other costs occasioned by water usage violations that are the proximate result of the Resident’s action. If any such fines or costs are levied against Owner/Agent, Resident agrees to pay such fines or costs attributed to Resident’s tenancy or the conduct of Resident, Resident’s guests or others at the premises. The obligation to pay fines and costs assessed against Owner/Agent may be in addition to any assessed directly against Resident.</li>
      <li>Resident agrees that Owner/Agent may provide Resident’s name and address to the local water agency for the purpose of notifications and enforcement of water use restrictions.</li>
      <li>Nothing herein is deemed to be authorization of or consent by Owner/Agent to water usage not authorized by the Rental/Lease Agreement.</li>
  </ol>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][10] !!}<br />{!! $data['agreement']['tenant_print_'][9] !!}</p>
  <h4><b>55.11 Water Sub-metering Addendum</b></h4>
  <table>
      <tr>
        <td width="10"></td>
        <td width="15"><b>1.</b></td>
        <td width="500"><p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p><p>{!! $data['agreement']['full_prop_address_6'] !!}</p></td>
      </tr>
  </table>
  <h5><b><i>Water Sub-meter</i></b></h5>
  <p>The above-described premises are equipped with a water sub-meter. As required by California law, Resident will be billed for water service separately from the rent. The location of the sub-meter for this unit is {!! $data['agreement']['submeter_location_1'] !!}</p>
  <h5><b><i>Estimated Water Bill</i></b></h5>
  <p>The estimated monthly bill for water service for dwelling units at the property is {!! $data['agreement']['submeter_cost_1'] !!}. This estimate is based on (check one)<br />({!! $data['agreement']['average_bill_1'] !!}) The average / median bill for water service for comparative dwelling units at the property over any three of the past six months.<br />({!! $data['agreement']['family_bill_1'] !!}) The average indoor water use of a family of four, and all other monthly charges that will be assessed. The average family of four uses about 200 gallons of water each day.</p>
  <h5><b><i>Due Date and Payment Procedures.</i></b></h5>
  <p>The water service bill is due on the {!! $data['agreement']['water_service_bill_day_1'] !!} day of each and every month, beginning on {!! $data['agreement']['submeter_date_1'] !!}. The bill must be paid to the person specified, and using one of the payment methods (e.g., check, money order) required, for the payment of rent under the Rental/Lease Agreement.</p>
  <h5><b><i>Contact for Water Service Billing Questions</i></b></h5>
  <p><i>Regarding the water service billing should be directed to:</i></p>
  <p>Name: {!! $data['agreement']['water_service_name_1'] !!}<br />Mailing Address: {!! $data['agreement']['water_service_address_1'] !!}<br />Email Address: {!! $data['agreement']['water_service_email_1'] !!}<br />Toll-free or Local Telephone Number: {!! $data['agreement']['water_service_phone_1'] !!}</p>
  <p>Regular telephone service at this number is available between the hours of {!! $data['agreement']['water_service_hour_from_1'] !!} and {!! $data['agreement']['water_service_hour_to_1'] !!} on the following days of the week:</p>
  <p>({!! $data['agreement']['water_service_day_monday_1'] !!}) Monday ({!! $data['agreement']['water_service_day_tuesday_1'] !!}) Tuesday ({!! $data['agreement']['water_service_day_wednesday_1'] !!}) Wednesday ({!! $data['agreement']['water_service_day_thursday_1'] !!}) Thursday ({!! $data['agreement']['water_service_day_friday_1'] !!}) Friday ({!! $data['agreement']['water_service_day_saturday_1'] !!}) Saturday ({!! $data['agreement']['water_service_day_sunday_1'] !!}) Sunday</p>
  <p>Other: {!! $data['agreement']['water_service_day_other_1'] !!}</p>
  <h5><b><i>Information Available on Request</i></b></h5>
  <p>Landlord shall provide information upon Resident’s request: (1) the calculations used to determine a monthly bill, (2) the date the sub meter was last certified for use, and the date it is next scheduled for certification (if known).</p>
  <table>
      <tr>
          <td width="5"></td>
          <td width="15"><b>2.</b></td>
          <td width="525"><h5><b>Allowable Charge</b></h5>The monthly bill for water service may only include the following charges:</td>
      </tr>
  </table>
  <table>
      <tr><td width="5"></td><td width="15">a.</td><td width="525">Payment due for the amount of usage as measured by the sub-meter and charged at allowable rates in accordance with Civil Code Section 1954.205(a).</td></tr>
      <tr><td width="5"></td><td width="15">b.</td><td width="525">Payment of a portion of the fixed fee charged by the water purveyors for water service.</td></tr>
      <tr><td width="5"></td><td width="15">c.</td><td width="525">A fee for the landlord’s or billing agent’s costs in accordance with Civil Code Section1954.205 (a) (3).</td></tr>
      <tr><td width="5"></td><td width="15">d.</td><td width="525">Any late fee, with the amounts and times assessed, in compliance with Civil Code Section 1954.213. A late fee of up to seven dollars ($7) may be imposed if any amount of a water service bill remains unpaid after 25 days following the date of mailing or other transmittal of the bill. If the 25th day falls on a Saturday, Sunday, or holiday, the late fee shall not be imposed until the day after the first business day following the 25th day. A late fee of up to ten dollars ($10) may be imposed in each subsequent bill if any amount remains unpaid.</td></tr>
  </table>
  <h5><b><i>Malfunctioning Water Fixtures</i></b></h5>
  <p>Resident shall notify the Landlord of any leaks, drips, water fixtures that do not shut off properly, including, but not limited to, a toilet, or other problems with the water system, including, but not limited to, problems with water-saving devices. Landlord is required to investigate, and, if necessary, repair these problems within 21 days, otherwise, the water bill will be adjusted pursuant to law. Notice of leaks, drips, and/or water fixtures that do not shut off properly must be provided to:</p>
  <p>Name: {!! $data['agreement']['water_fixture_name_1'] !!}<br />Mailiing Address: {!! $data['agreement']['water_fixture_address_1'] !!}<br />Email Address: {!! $data['agreement']['water_fixture_email_1'] !!}<br />Toll-free or Local Telephone Number: {!! $data['agreement']['water_fixture_phone_1'] !!}</p>
  <h5><b><i>Inaccurate or Malfunctioning Sub-meter</i></b></h5>
  <p>If Resident believes that the sub-meter reading is inaccurate or the sub-meter is malfunctioning, Resident shall first notify the person specified in paragraph 7 and request an investigation. If an alleged sub-meter malfunction is not resolved by Landlord, Resident may contact the local county sealer and request that the sub-meter be tested. Contact information for the local county sealer of weights and measures: </p>
  <p>Name: {!! $data['agreement']['submeter_name_1'] !!}<br />Mailing Address: {!! $data['agreement']['submeter_address_1'] !!}<br />Email Address: {!! $data['agreement']['submeter_email_1'] !!}<br />Toll-free or Local Telephone Number: {!! $data['agreement']['submeter_phone_1'] !!}</p>
  <h5><b>Additional Information</b></h5>
  <p>This disclosure is only a general overview of the laws regarding sub-meters and the laws regarding sub-meters that can be found at Chapter 2.5 (commencing with Section 1954.201) of Title 5 of Part 4 of Division 3 of the Civil Code, available online or at most libraries.</p>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][10] !!}<br />{!! $data['agreement']['tenant_print_'][9] !!}</p>
  <h4><b>55.12 Renter Insurance Addendum</b></h4>
  <p>Resident is encouraged to obtain Renter's insurance.<br />Insured Tenants must maintain their Renter's Insurance throughout the duration of the tenancy that includes all of the following:</p>
  <table>
      <tr><td width="15">1.</td><td width="520">Coverage of at least $15,000 in personal liability (bodily injury and property damage) for EACH occurrence;</td></tr>
      <tr><td width="15">2.</td><td width="520">The premises listed above must be listed as the insured location and resident;</td></tr>
      <tr><td width="15">3.</td><td width="520">Landlord is listed as a Certificated Holder;</td></tr>
      <tr><td width="15">4.</td><td width="520">Notification that the Carrier must provide 30 days' notice of cancellation, non-renewal, or material change in coverage, to the Owner/Agent.</td></tr>
  </table>
  <p><b><u>Insurance Facts for Residents</u></b></p>
  <table>
      <tr><td width="15">1.</td><td width="525">Generally, except under special circumstances, the OWNER IS NOT legally responsible for loss to the Resident's personal property, possessions or personal liability, and OWNER'S INSURANCE WILL NOT COVER such losses or damages.</td></tr>
      <tr><td width="15">2.</td><td width="525">If damage or injury to OWNER'S PROPERTY is caused by resident(s), resident's guest(s), the OWNER'S insurance company may have right to attempt to recover from the Resident(s) payments made under the Owner's policy.</td></tr>
  </table>
  <table>
      <tr><td width="15">3.</td><td width="525">If you the tenant(s) desire to protect yourself and your property against loss, damage, and/or liability, the Owner /strongly recommends you consult with your insurance agent and obtain appropriate coverage for fire, theft, liability, workers' compensation and other perils.</td></tr>
  </table>
  <p>The cost is reasonable considering the peace of mind, the protection, and the financial recovery off loss that you get if you are adequately protected by insurance.</p>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][10] !!}<br />{!! $data['agreement']['tenant_print_'][9] !!}</p>
  <h4><b>55.13 Satellite Dish and Antenna Addendum</b></h4>
  <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
  <p>{!! $data['agreement']['full_prop_address_7'] !!}</p>
  <p>Under the rules of the Federal Communications Commission (FCC), Owners/Agents may not prohibit the installation of satellite dishes and/or receiving antennas within leased premises. However, an Owner/Agent may impose reasonable restrictions relating to the installation and maintenance of any satellite dish and receiving antenna with which a resident must comply as a condition of installing such equipment.<br />Resident agrees to comply with the following restrictions:</p>
  <ol>
      <li>Size: A satellite dish may not exceed 39 inches (1 meter) in diameter. An antenna or dish may receive but not transmit signals.</li>
      <li>Location: A satellite dish or antenna may only be located (1) inside Resident's dwelling, or (2) in an area outside Resident's dwelling such as Resident's balcony, patio, yard, etc., of which Resident has exclusive use under the lease. Installation is not permitted on any parking area, roof, exterior wall, window, fence or common area, or in an area that other Residents are allowed to use. A satellite dish or antenna may not protrude beyond the vertical and horizontal space that is leased to Resident for Resident's exclusive use. Allowable locations may not provide optimum signal. Owner/Agent is not required to provide alternate locations if allowable locations are not suitable.</li>
      <li>Safety and Non-Interference:  Satellite dish/antenna installation: (1) must comply with reasonable safety standards; (2) may not interfere with Owner/Agent's cable, telephone or electrical systems or those of neighboring properties. It may not be connected to Owner/Agent's telecommunication systems, and may not be connected to Owner/Agent's electrical system except by plugging into a 110-volt duplex receptacle.</li>
      <li>Outside Installation: If a satellite dish or antenna is placed in a permitted area outside the dwelling unit, it must be safely secured by one of three methods: (1) securely attaching to a portable, heavy object; (2) clamping it to a part of the building's exterior that lies within Resident's leased premises (such as a balcony or patio railing) or (3) any other method approved by Owner/Agent. No other methods are allowed. Owner/Agent may require that Resident block a satellite dish or antenna with plants, etc., so long as it does not impair Resident's reception.</li>
      <li>Signal Transmission from Outside Installation: If a satellite dish or antenna is installed outside the dwelling unit, signals may be transmitted to the interior of Resident's dwelling only by: (1) running a "flat" cable under a door jam or window sill in a manner that does not physically alter the premises and does not interfere with proper operation of the door or window; (2) running a traditional or flat cable through a pre-existing hole in the wall (that will not need to be enlarged to accommodate the cable); or (3) any other method approved by Owner/Agent.</li>
      <li>Installation and Workmanship: For safety purposes, Resident must obtain Owner/Agent's approval of (1) the strength and type of materials to be used for installation and (2) the person or company who will perform the installation. Installation must be done by a qualified person or a company that has workers' compensation insurance and adequate public liability insurance. Owner/Agent's approval will not be unreasonably withheld. Resident must obtain any permits required by local ordinances for the installation and must comply with any applicable local ordinances and state laws. Resident may not damage or alter the leased premises and may not drill holes through outside walls, door jams, window sills, etc., to install a satellite dish, antenna, and related equipment.</li>
      <li>Maintenance: Resident will have the sole responsibility for maintaining a satellite dish or antenna and all related equipment. Owner/Agent may temporarily remove any satellite dish or antenna if necessary to make repairs to the building.</li>
      <li>Removal and Damages:  Any satellite dish, antenna, and all related equipment must be removed by the Resident when Resident moves out of the dwelling. Resident must pay for any damages and for the cost of repairs or repainting that may be reasonably necessary to restore the leased premises to its condition prior to the installation of a satellite dish or antenna and related equipment.</li>
      <li>Liability Insurance and Indemnity: Resident is fully responsible for any satellite dish or antenna and related equipment. Owner/Agent ({!! $data['agreement']['require_insurance_1'] !!}) does ({!! $data['agreement']['not_require_insurance_1'] !!}) does not require evidence of liability insurance. If Owner/Agent does require insurance, prior to installation, Resident must provide Owner/Agent with evidence of liability insurance to protect Owner/Agent against claims of personal injury to others and property damage related to Resident's satellite dish, antenna, or related equipment. The insurance coverage must be no less than {!! $data['agreement']['satellite_insurance_1'] !!} (which is an amount reasonably determined by Owner/Agent to accomplish that purpose) and must remain in force while the satellite dish or antenna remains installed. Resident agrees to defend, indemnify, and hold Owner/Agent harmless from the above claims by others.</li>
      <li>Deposit Increase. Owner/Agent ({!! $data['agreement']['require_satellites_deposit_1'] !!}) does ({!! $data['agreement']['not_require_satellite_deposit_1'] !!}) does not require an additional security deposit (in connection with having a satellite dish or antenna): If Owner/Agent does require an increased deposit, Resident agrees to pay an additional security deposit in the amount of {!! $data['agreement']['satellite_deposit_1'] !!} to help protect Owner/Agent against possible repair costs, damages, or any failure to remove the satellite dish or antenna and related equipment at the time of move-out. A security deposit increase does not imply a right to drill into or alter the leased premises. In no case will the total amount of all security deposits Resident pays to Owner/Agent be more than that which is allowed by law (two times the amount of rent for an unfurnished unit and three times the amount of rent for a furnished unit).</li>
      <li>When Resident may begin Installation: Resident may start installation of a satellite dish or antenna only after Resident has: (1) signed this addendum; (2) provided Owner/Agent with written evidence of the liability insurance referred to in paragraph 9 of this addendum, if applicable; (3) paid Owner/Agent the additional security deposit, if applicable, referred to in paragraph 10; and (4) received Owner/Agent's written approval of the installation materials and the person or company who will do the installation.</li>
  </ol>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][11] !!}<br />{!! $data['agreement']['tenant_print_'][10] !!}</p>
  <h4><b>55.14 Crime Free Lease Addendum</b></h4>
  In consideration of the execution or renewal of a lease for the Identified property in the lease agreement, the OWNER and RESIDENT(S) agree as follows:<ol>
      <li>Resident, any members of the resident's household or guest, or other person under the resident's control, shall not engage in any criminal activity, including drug-related activity, on or near the premises. "Drug related criminal activity" means the illegal manufacture, sale, distribution, use, or possession with intent to manufacturer, sell, distribute, or use of a controlled substance as defined in Section 102 of the Controlled Substance Act [21 U.S.C. 802}. Medically prescribed marijuana (cannabis) use is allowed, only if used in the prescribed manner (California Residents Only). See the lease agreement stipulations In regard to "No Smoking" areas, or Non-Smoking buildings.</li>
      <li>Resident or members of the household will not permit the dwelling unit to be used for, or to facilitate criminal activity of any kind. This includes the unlawful manufacturing, selling, using, storing or giving of a controlled substance as defined In the Health & Safety Code, 11350, et seq., anywhere on or near the premises.</li>
      <li>Criminal activity means any activity that is subject to criminal prosecution, either felony or misdemeanor. These Include, but are not limited to: prostitution, criminal street gang activity, assault and battery, burglary, theft, unlawful possession and use of firearms, sexual offenses, and vandalism (See the No-Nuisance Lease Addendum for Crimes that disturb the peace).</li>
      <li>Violation of the above provisions shall be a mater/al and Irreparable violation of the lease, and good cause for immediate termination of the tenancy. A single Violation of any of the provisions of this addendum shall be deemed as serious, and a material and irreparable non-compliance, subject to a Three Day Notice to Quit. Unless otherwise provided by law, proof of a violation shall not require criminal conviction, but shall be by a preponderance of the evidence via witnesses, or by arrest Information supplied by the arresting agency.</li>
      <li>In case of conflict between the provisions of this addendum and any other provisions of the lease, the addendum shall govern.</li>
      <li>This LEASE ADDENDUM Is Incorporated Into the lease executed or renewed this day between OWNER and RESIDENT(S).</li>
  </ol>
  <p>By signing below, the undersigned Resident(s) agree and acknowledge having read and understood this addendum.</p>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][11] !!}{!! $data['agreement']['tenant_print_'][10] !!}</p>
  <h4><b>55.15 No-Nuisance Lease Addendum</b></h4>
  <p>Because we (Owner/Manager/Agent) believe that your rental unit is your "Home•, and your neighbor's unit/house is 'their home, we greatly appreciate your cooperation in maintaining the peace and quiet in the community.</p>
  <p>In the execution of, or renewal of the dwelling unit Identified In the lease, Owner and Resident agree as follows:</p>
  <ol>
      <li>Resident, any members of the resident's household or a guest of the household shall not engage in any nuisance activity that threatens the health, safety, or the right to peaceful enjoyment of life and property of any neighbor in the vicinity.</li>
      <li>Resident agrees not to make permit disturbing noises (e.g. hooting, yelling, shouting, music In a vehicle, excessive engine noises, fighting, loud parties, loud televisions or music); or any other disturbing noises that constitute a violation of Section 415 of the C811fornla Penal Code [Disturbing The Peace).</li>
      <li>If you are allowed to have pets in the lease, you shall ensure that they are not a nuisance to the community which would cause the Police or Animal Control calls, including excessive barking, maintaining and cleaning up after them for sanitation, health, and safety purposes</li>
      <li>No parking or storing of unused or unregistered/Inoperable vehicles or RV's on the premises.</li>
  </ol>
  <p>GROUND FOR TERMINATION OF THE LEASE</p>
  <p>Three occasions of nuisance activity by resident(s),members of the resident's household, or guests of any members of the household WITHIN A SIX MONTH PERIOD WILL CONSTITUTE A MATERIAL AND IRREPARABLE  VIOLATION OF THE LEASE ANP WILL BE A GOOD CAUSE FOR TERMI NATION OF THE TENANCY. It is also understood that proof of a violation shall not require criminal conviction. But shall be by a preponderance of the evidence.<br />In keeping with FAIR HOUSING and NON-DISCRIMINATORY statutes, you cannot be evicted for nuisance activity unless It Is substantiated by witnesses and/or Police response logs that describe Police action or arrests.</p>
  <p>Landlords are required by Civil and Criminal Law to maintain their properties in a manner that is conductive to the peaceful enjoyment of life in the community. This is accomplished by lease enforcement and eviction of tenants who cause a public nuisance, or continued criminal nuisance behavior. {California Civil Code 3479-3491 et. al and 373(a) of the Ca. Penal Code].</p>
  <p>Domestic Violence is not Included In this addendum.<br />Incorporated Into the lease this day between OWNER and RESIDENT(S). In case of a conflict between the provisions of this addendum and any other provisions of the lease, the addendum shall govern.</p>
  <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
  <p>{!! $data['agreement']['tnt_signature_'][12] !!}{!! $data['agreement']['tenant_print_'][11] !!}</p>
  <h4><b>55.16. Yard & Landscape Maintenance Addendum (If Applicable)</b></h4>
    <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
    <p>{!! $data['agreement']['full_prop_address_8'] !!}</p>
    <p>Tenant and Landlord hereby enter into this Yard & Landscape Maintenance Addendum ("the Addendum") concurrently with and effective as of the date of the Agreement.<br>Tenant hereby agrees and acknowledges that he/she will maintain the yard area and related landscape located at the Premises during the Agreement Term (collectively, "Landscape Maintenance"). The Landscape Maintenance shall be performed by Tenant in strict conformance with and pursuant to the following standards:</p>
  <table>
        <tr>
            <td class="full-cell">
                <table>
                    <tr>
                        <td width="5"></td>
                        <td width="20">2.</td>
                        <td width="500"><p><b>Lawn Watering:</b> Tenant must sufficiently water all lawn and grass areas ("Lawn") as needed to keep the Lawn green. To minimize evaporation and promote deeper saturation, the recommended watering time is early in the morning (4-8 AM). During the spring and summer the recommended watering frequency for is 4-5 days per week for 8 minutes. Depending on rainfall, during the fall and winter the frequency of watering can be reduced to 1-2 days per week for 8 minutes.</p></td>
                    </tr>
                    <tr>
                        <td width="5"></td>
                        <td width="20">3.</td>
                        <td width="500"><p><b>Lawn Maintenance:</b>Tenant must perform all mowing and edging. The Lawn should be mowed weekly during the spring and summer and every other week in the fall and winter. Tenant should edge around the driveway, sidewalks and planter areas at the same time the Lawn is mowed.  All debris and grass clippings must be blown off or swept clean from the driveway and surrounding yard and placed in the trash or a green recycling/compost bin.</p></td>
                    </tr>
                    <tr>
                        <td width="5"></td>
                        <td width="20">4.</td>
                        <td width="500"><p><b>Lawn Maintenance:</b>Tenant must perform all mowing and edging. The Lawn should be mowed weekly during the spring and summer and every other week in the fall and winter. Tenant should edge around the driveway, sidewalks and planter areas at the same time the Lawn is mowed.  All debris and grass clippings must be blown off or swept clean from the driveway and surrounding yard and placed in the trash or a green recycling/compost bin.</p></td>
                    </tr>
                    <tr><td width="5"></td><td width="20">5.</td><td width="500"><p><b>Plant Watering and Maintenance: </b>Tenant must sufficiently water all planted areas ("Plants") as needed to keep plants green and growing and remove weeds. To minimize evaporation and promote deeper saturation, the best watering time is early morning (4-8 AM). The recommended watering frequency is 4-5 days per week for 10 minutes. In fall and winter the frequency can be reduced to 1-2 days per week for 8 minutes.</p></td></tr>
                    <tr><td width="5"></td><td width="20">6.</td><td width="500"><p><b>Irrigation Systems: </b>Tenant’s Premises may be equipped with an automatic sprinkler system and timer. If the system contains a timer, it should already be set to water at the appropriate time and frequency. Please be aware of warning signs that your sprinkler system might need adjustment. Inspect the lawn and plants bi-weekly for mold or excessive yellow coloration. These can be signs that the system is incorrect or malfunctioning.</p></td></tr>
                    <tr><td width="5"></td><td width="20">7.</td><td width="500"><p><b>Basic Maintenance: </b>Tenant should check the irrigation system regularly to ensure that it is working properly. Tenant must replace any damaged or broken sprinkler heads, mini sprayers, or drip lines and/or accessories with parts of "like kind". All replacement parts are readily available at your local Home Depot or equivalent hardware store.</p></td></tr>
                    <tr><td width="5"></td><td width="20">8.</td><td width="500"><p><b>Shrubs & Hedges: </b>Tenant must trim or prune all shrubs and hedges as needed and necessary to prevent excessive or unkempt growth. In the event the Premises is at the corner of intersecting streets, Tenant must keep all hedges or shrubs trimmed to a height that does not obstruct or obscure traffic visibility across the Premises.   Barring any local law or ordinance to the contrary, the height of shrubs and hedges in the front yard of a corner lot should not exceed two and one half feet.</p></td></tr>
                    <tr><td width="5"></td><td width="20">9.</td><td width="500"><p><b>Optional outside Landscaping Services: </b>Tenant may wish and is permitted to hire a landscape service company to do some or all of the yard and landscape maintenance for Tenant.</p></td></tr>
                    <tr><td width="5"></td><td width="20">10.</td><td width="500"><p><b>Back Yards: </b>Tenant is permitted to landscape back yard at their discretion. They must submit plans in writing to Landlord and Landlord must approve before work can commence.</p></td></tr>
                    <tr><td width="5"></td><td width="20">11.</td><td width="500"><p><b>Periodic Inspections: </b>Landlord will conduct twice-yearly exterior inspections of the Landscape Maintenance. Tenants whose Landscape Maintenance is not consistent with the guidelines set forth in this Addendum may be in default of the Rental Agreement.</p></td></tr>
                </table>
<!--                <ol start="2">
                    <li><p><b>Lawn Watering:</b> Tenant must sufficiently water all lawn and grass areas ("Lawn") as needed to keep the Lawn green. To minimize evaporation and promote deeper saturation, the recommended watering time is early in the morning (4-8 AM). During the spring and summer the recommended watering frequency for is 4-5 days per week for 8 minutes. Depending on rainfall, during the fall and winter the frequency of watering can be reduced to 1-2 days per week for 8 minutes.</p></li>
                    <li><p><b>Lawn Maintenance:</b>Tenant must perform all mowing and edging. The Lawn should be mowed weekly during the spring and summer and every other week in the fall and winter. Tenant should edge around the driveway, sidewalks and planter areas at the same time the Lawn is mowed.  All debris and grass clippings must be blown off or swept clean from the driveway and surrounding yard and placed in the trash or a green recycling/compost bin.</p></li>
                    <li><p><b>Plant Watering and Maintenance: </b>Tenant must sufficiently water all planted areas ("Plants") as needed to keep plants green and growing and remove weeds. To minimize evaporation and promote deeper saturation, the best watering time is early morning (4-8 AM). The recommended watering frequency is 4-5 days per week for 10 minutes. In fall and winter the frequency can be reduced to 1-2 days per week for 8 minutes.</p></li>
                    <li><p><b>Irrigation Systems: </b>Tenant’s Premises may be equipped with an automatic sprinkler system and timer. If the system contains a timer, it should already be set to water at the appropriate time and frequency. Please be aware of warning signs that your sprinkler system might need adjustment. Inspect the lawn and plants bi-weekly for mold or excessive yellow coloration. These can be signs that the system is incorrect or malfunctioning.</p></li>
                    <li><p><b>Basic Maintenance: </b>Tenant should check the irrigation system regularly to ensure that it is working properly. Tenant must replace any damaged or broken sprinkler heads, mini sprayers, or drip lines and/or accessories with parts of "like kind". All replacement parts are readily available at your local Home Depot or equivalent hardware store.</p></li>
                    <li><p><b>Shrubs & Hedges: </b>Tenant must trim or prune all shrubs and hedges as needed and necessary to prevent excessive or unkempt growth. In the event the Premises is at the corner of intersecting streets, Tenant must keep all hedges or shrubs trimmed to a height that does not obstruct or obscure traffic visibility across the Premises.   Barring any local law or ordinance to the contrary, the height of shrubs and hedges in the front yard of a corner lot should not exceed two and one half feet.</p></li>
                    <li><p><b>Optional outside Landscaping Services: </b>Tenant may wish and is permitted to hire a landscape service company to do some or all of the yard and landscape maintenance for Tenant.</p></li>
                    <li><p><b>Back Yards: </b>Tenant is permitted to landscape back yard at their discretion. They must submit plans in writing to Landlord and Landlord must approve before work can commence.</p></li>
                    <li><p><b>Periodic Inspections: </b>Landlord will conduct twice-yearly exterior inspections of the Landscape Maintenance. Tenants whose Landscape Maintenance is not consistent with the guidelines set forth in this Addendum may be in default of the Rental Agreement.</p></li>
                </ol>-->
            </td>
        </tr>
    </table>
    <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
    <p>{!! $data['agreement']['tnt_signature_'][13] !!}<br />{!! $data['agreement']['tenant_print_'][12] !!}</p>
    <h4><b>55.17. Pool, Hot Tub, and Spa Addendum (If Applicable)</b></h4>
    <p>The following terms and conditions are hereby incorporated in and made a part of the Residential Lease or Month-to-Month Rental Agreement, on property located at: (<u>Property Address</u>, <u>Property Number</u>, <u>Unit Number</u>)</p>
    <p>{!! $data['agreement']['full_prop_address_6'] !!}</p>

    <p>Pools and Hot Tubs:</p>
    <table>
        <tr>
            <td width="10"></td>
            <td width="10">1.</td>
            <td width="525">Swimming pools, hot tubs, and spas, while providing exercise, recreation, and relaxation, also can be dangerous. People (as well as pets) can be severely injured or drown if the pool, hot tub or spa is not properly used. Tenants are strongly cautioned that they, other occupants and tenant's guest must adhere to the following safe practices:</td>
        </tr>
    </table>
    <table>
        <tr><td width="20"></td><td width="10">a.</td><td width="500">No diving into the pool or hot tub or spa</td></tr>
        <tr><td width="20"></td><td width="10">b.</td><td width="500">No intoxicated persons may use the pool or hot tub or spa</td></tr>
        <tr><td width="20"></td><td width="10">c.</td><td width="500">No one should use the pool or hot tub or spa alone</td></tr>
        <tr><td width="20"></td><td width="10">d.</td><td width="500">All persons must have adequate swimming ability to use the pool / spa or be accompanied by a person with adequate swimming ability.</td></tr>
        <tr><td width="20"></td><td width="10"><b>e.</b></td><td width="500"><b>Tenant shall be responsible for making sure the self-latching gate into the pool area is fully functional at all times, that it is closed and latched at all time.</b></td></tr>
    </table>
    <p>Neither the landlord nor the landlord's agents can assure the safety of persons using property containing a pool, hot tub or spa. As a consequence, tenants assume liability for pool, hot tub or spa use by themselves, other occupants, their guests, and their pets.</p>
    <table>
        <tr>
            <td width="10"></td>
            <td width="10">2.</td>
            <td width="525">If the rental is part of a rental complex, the following also apply:</td>
        </tr>
    </table>
    <table>
        <tr><td width="20"></td><td width="10">a.</td><td width="500">The pool, hot tub or spa may only be used during posted hours.</td></tr>
        <tr><td width="20"></td><td width="10">b.</td><td width="500">All drinks must be served in unbreakable containers.</td></tr>
        <tr><td width="20"></td><td width="10">c.</td><td width="500">No alcoholic drinks are allowed in the pool area, hot tub or spa.</td></tr>
        <tr><td width="20"></td><td width="10">d.</td><td width="500">No excessive noise - please be considerate of your neighbors.</td></tr>
        <tr><td width="20"></td><td width="10">e.</td><td width="500">Users must shower prior to using the pool, hot tub or spa.</td></tr>
        <tr><td width="20"></td><td width="10">f.</td><td width="500">Use the pool safety equipment only in case of emergency.</td></tr>
        <tr><td width="20"></td><td width="10">g.</td><td width="500">HOA or House Rules, if applicable, will supplement or supersede these rules.</td></tr>
    </table>
    <table>
        <tr>
            <td width="10"></td>
            <td width="10">3.</td>
            <td width="525">NO LIFEGUARD WILL BE ON DUTY - YOU SWIM AT YOUR OWN RISK</td>
        </tr>
        <tr>
            <td></td>
            <td>4.</td>
            <td>Tenant agrees to release, indemnify, hold harmless and forever discharge Landlord and Landlord's employees, agents, successors and assigns from any and all claims, liabilities or causes of action of any kind that Tenant, members of Tenant's household or Tenant's guests or invitees may have at any time against Landlord or Landlord's agents resulting from Tenant's use of the pool, hot tub or spa.</td>
        </tr>
    </table>
    <p><b>THE UNDERSIGNED TENANT(S) ACKNOWLEDGES HAVING READ AND UNDERSTOOD THE FOREGOING, AND RECEIPT OF A DUPLICATE ORIGINAL. TENANT(S) AGREE TO RENT THE PREMISES ON THE ABOVE TERMS AND CONDITIONS.</b></p>
    <p>{!! $data['agreement']['tnt_signature_'][14] !!}<br />{!! $data['agreement']['tenant_print_'][13] !!}</p>
        
        
        
