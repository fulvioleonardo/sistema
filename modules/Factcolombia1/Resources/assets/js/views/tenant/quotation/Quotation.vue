<template>
  <main class="content">
    <div class="container-fluid">
      <v-app id="document">
        <div class="content-header">
          <h1>Listado de cotizaciones</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <v-btn
                  :href="`${resource}/create`"
                  class="bee darker text-white no-decoration"
                >Nueva Cotización</v-btn>
                <!-- <v-btn color="success" @click="dialog = !dialog">Nuevo documento</v-btn> -->
              </li>
            </ol>
          </nav>
        </div>
        <v-card>
          <v-card-title>
            <v-spacer></v-spacer>
            <v-spacer></v-spacer>
            <v-text-field
              v-model="search"
              append-icon="search"
              label="Buscar"
              single-line
              hide-details
            ></v-text-field>
          </v-card-title>
          <v-data-table
            :headers="columns"
            :items="documents"
            :search="search"
            :loading="loadDataTable"
            :rows-per-page-items="[20, 50, 100, 500, 1000]"
            class="elevation-1"
          >
            <template slot="items" slot-scope="props">
              <td>{{props.item.id}}</td>
              <td>{{props.item.created_at}}</td>
              <td>{{props.item.client.name}}</td>
              <td>{{props.item.state_quote.name}}</td>
              <td>{{props.item.currency.code}}</td>
              <td>{{ratePrefix()}}{{props.item.sale | numberFormat}}</td>
              <td>{{ratePrefix()}}{{props.item.total_discount | numberFormat}}</td>
              <td>{{ratePrefix()}}{{props.item.total_tax | numberFormat}}</td>
              <td>{{ratePrefix()}}{{props.item.subtotal | numberFormat}}</td>
              <td>{{ratePrefix()}}{{props.item.total | numberFormat}}</td>
              <td>
                <v-icon
                  v-if="props.item.state_quote.id == 1"
                  small
                  @click="editQuotation(props.item)"
                  :disabled="props.item.toBill"
                >edit</v-icon>
                <v-icon small @click="downloadPDF(props.item)">fas fa-file-pdf</v-icon>
                <v-icon
                  v-if="props.item.state_quote.id == 1"
                  small
                  @click="toBill(props.item)"
                  :disabled="props.item.toBill"
                >fas fa-file-alt</v-icon>
              </td>
            </template>
          </v-data-table>
        </v-card>
      </v-app>
      <v-app id="document_form" style="height: 0px;">
        <v-layout row justify-center>
          <v-dialog v-model="dialog" fullscreen hide-overlay transition="dialog-bottom-transition">
            <v-card>
              <v-toolbar color="primary">
                <v-btn icon @click="close()">
                  <v-icon class="text-white">close</v-icon>
                </v-btn>
                <v-toolbar-title class="text-white">{{title}}</v-toolbar-title>
                <v-spacer></v-spacer>
                <v-toolbar-items>
                  <v-btn flat @click="dialogItems = !dialogItems" class="text-white">
                    <v-icon>add</v-icon>Agregar productos
                  </v-btn>
                  <v-btn
                    v-if="editMode"
                    flat
                    :loading="loading"
                    @click="validateEdit('document')"
                    :disabled="!showTotalAndSave"
                    class="text-white"
                  >Modificar</v-btn>
                  <v-btn
                    v-else
                    flat
                    :loading="loading"
                    @click="validate('document')"
                    :disabled="!showTotalAndSave"
                    style="color:white !important;"
                  >Guardar</v-btn>
                </v-toolbar-items>
              </v-toolbar>
              <v-list three-line subheader>
                <v-form data-vv-scope="document">
                  <v-container>
                    <v-layout row wrap>
                      <v-flex xs12 sm12 md6 lg6 xl6>
                        <v-autocomplete
                          v-model="document.client_id"
                          v-validate="'required'"
                          :error-messages="errors.collect('document.client_id')"
                          data-vv-name="client_id"
                          :counter="255"
                          :items="clients"
                          item-text="name"
                          item-value="id"
                          label="Cliente *"
                          required
                        ></v-autocomplete>
                      </v-flex>
                      <v-flex xs12 sm12 md6 lg6 xl6>
                        <v-autocomplete
                          v-model="document.currency_id"
                          v-validate="'required'"
                          :error-messages="errors.collect('document.currency_id')"
                          data-vv-name="currency_id"
                          :counter="255"
                          :items="currencies"
                          item-text="name"
                          item-value="id"
                          label="Moneda *"
                          required
                        ></v-autocomplete>
                      </v-flex>
                      <v-flex xs12 sm12 md12 lg12 xl12>
                        <v-data-table :headers="columnItems" :items="document.items" hide-actions>
                          <template slot="items" slot-scope="props">
                            <td>{{props.item.name}}</td>
                            <td>{{props.item.type_unit.name}}</td>
                            <td>{{props.item.quantity}}</td>
                            <td>{{ratePrefix()}}{{props.item.price}}</td>
                            <td>
                              <v-autocomplete
                                v-model="props.item.tax_id"
                                :suffix="`${ratePrefix()}${props.item.total_tax}`"
                                :counter="255"
                                :items="itemTaxes"
                                item-text="name"
                                item-value="id"
                              ></v-autocomplete>
                            </td>
                            <td>{{ratePrefix()}}{{props.item.subtotal}}</td>
                            <td>
                              <v-text-field
                                v-model="props.item.discount"
                                v-validate="'decimal:2|max:13'"
                                :prefix="currencySymbol"
                                :error-messages="errors.collect('document.discount')"
                                data-vv-name="discount"
                                :counter="13"
                                label="Descuento"
                                required
                              ></v-text-field>
                            </td>
                            <td>{{ratePrefix()}}{{props.item.total}}</td>
                            <td class="text-lg-right text-md-right text-sm-right text-xs-right">
                              <v-icon small @click="deleteItem(props.item)">delete</v-icon>
                            </td>
                          </template>
                        </v-data-table>
                      </v-flex>
                      <v-flex v-if="showTotalAndSave" xs12 sm12 md12 lg12 xl12>
                        <v-layout justify-end>
                          <v-btn block @click="dialogRetention = !dialogRetention">
                            <v-icon>add</v-icon>Agregar retención
                          </v-btn>
                        </v-layout>
                        <v-layout justify-end>
                          <div
                            class="text-uppercase title"
                          >Total venta: {{ratePrefix()}}{{document.sale}}</div>
                        </v-layout>
                        <v-layout justify-end>
                          <div
                            class="text-uppercase title"
                          >Total descuento(-): {{ratePrefix()}}{{document.total_discount}}</div>
                        </v-layout>
                        <template v-for="(tax, index) in document.taxes">
                          <v-layout v-if="((tax.total > 0) && (!tax.is_retention))" justify-end>
                            <div
                              class="text-uppercase title"
                            >{{tax.name}}(+): {{ratePrefix()}}{{Number(tax.total).toFixed(2)}}</div>
                          </v-layout>
                        </template>
                        <v-layout justify-end>
                          <div
                            class="text-uppercase title"
                          >Subtotal: {{ratePrefix()}}{{document.subtotal}}</div>
                        </v-layout>
                        <template v-for="(tax, index) in taxes">
                          <v-layout v-if="((tax.is_retention) && (tax.apply))" justify-end>
                            <v-flex xs12 sm4 md2 lg2 xl2>
                              <v-text-field
                                :prefix="currencySymbol"
                                :counter="13"
                                :label="`${tax.name}(-)`"
                                :value="tax.retention"
                                append-icon="close"
                                @click:append="deleteRetention(tax)"
                                readonly
                              ></v-text-field>
                            </v-flex>
                          </v-layout>
                        </template>
                        <v-layout justify-end>
                          <div
                            class="text-uppercase title"
                          >Total: {{ratePrefix()}}{{document.total}}</div>
                        </v-layout>
                      </v-flex>
                    </v-layout>
                  </v-container>
                </v-form>
              </v-list>
            </v-card>
          </v-dialog>
          <v-dialog v-model="dialogItems" max-width="600px">
            <v-card>
              <v-list three-line subheader>
                <v-form data-vv-scope="add">
                  <v-container>
                    <v-layout row wrap>
                      <v-flex xs12 sm12 md6 lg6>
                        <v-autocomplete
                          v-model="add.item_id"
                          v-validate="'required'"
                          :error-messages="errors.collect('add.item_id')"
                          data-vv-name="item_id"
                          :counter="255"
                          :items="items"
                          item-text="name"
                          item-value="id"
                          label="Producto *"
                          required
                        ></v-autocomplete>
                      </v-flex>
                      <v-flex xs12 sm12 md6 lg6>
                        <v-text-field
                          v-model="add.quantity"
                          v-validate="'required|decimal:2|max:13'"
                          :error-messages="errors.collect('add.quantity')"
                          data-vv-name="quantity"
                          :counter="13"
                          label="Cantidad *"
                          required
                        ></v-text-field>
                      </v-flex>
                    </v-layout>
                  </v-container>
                </v-form>
              </v-list>
              <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn flat @click="dialogItems = false">Cerrar</v-btn>
                <v-btn flat @click="validateItem('add')">Agregar</v-btn>
              </v-card-actions>
            </v-card>
          </v-dialog>
          <v-dialog v-model="dialogRetention" max-width="600px">
            <v-card>
              <v-list three-line subheader>
                <v-form data-vv-scope="retention">
                  <v-container>
                    <v-layout row wrap>
                      <v-flex xs12 sm12 md12 lg12>
                        <v-autocomplete
                          v-model="retention.retention_id"
                          :suffix="`${retentionSelected.rate}%`"
                          v-validate="'required'"
                          :error-messages="errors.collect('retention.retention_id')"
                          data-vv-name="retention_id"
                          :counter="255"
                          :items="retentiontaxes"
                          item-text="name"
                          item-value="id"
                          label="Retención *"
                          required
                        ></v-autocomplete>
                      </v-flex>
                    </v-layout>
                  </v-container>
                </v-form>
              </v-list>
              <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn flat @click="dialogRetention = false">Cerrar</v-btn>
                <v-btn flat @click="validateRetention('retention')">Agregar</v-btn>
              </v-card-actions>
            </v-card>
          </v-dialog>
        </v-layout>
      </v-app>
    </div>
  </main>
</template>

<script>
import Document from "../../tenant/document/Document.vue";

export default {
  mixins: [Document],
  props: {
    route: {
      required: true
    },
    api: {
      required: true
    }
  },
  computed: {
    title() {
      return this.editMode ? "Edición cotización" : "Nueva cotización";
    }
  },
  data: () => ({
    resource: "quotations",
    columns: [
      {
        text: "#",
        value: "id"
      },
      {
        text: "Fecha de registro",
        value: "created_at"
      },
      {
        text: "Cliente",
        value: "client.name"
      },
      {
        text: "Estado",
        value: "number"
      },
      {
        text: "Moneda",
        value: "currency.code"
      },
      {
        text: "Total venta",
        value: "sale"
      },
      {
        text: "Total descuentos",
        value: "total_discount"
      },
      {
        text: "Total impuestos",
        value: "total_tax"
      },
      {
        text: "Subtotal",
        value: "subtotal"
      },
      {
        text: "Total",
        value: "total"
      },
      {
        text: "Acciones",
        value: "actions",
        sortable: false
      }
    ],
    tax_amount_calculate: 0
  }),
  methods: {
    editQuotation(quotation) {
      let quotationClone = JSON.parse(JSON.stringify(quotation));
      let items = quotationClone.detail_quotations.map(row => row.item);

      this.taxes.forEach(tax => {
        let taxClone = quotationClone.taxes.find(row => row.id == tax.id);

        if (taxClone != null) tax.apply = taxClone.apply;
      });

      items.forEach(item => {
        this.$delete(item, "total_tax");
        this.$delete(item, "subtotal");
        this.$delete(item, "total");
        this.$delete(item, "tax");
      });

      quotationClone.taxes.forEach(tax => {
        tax.retention = 0;
        tax.total = 0;
      });

      quotationClone = {
        type_document_id: this.typeDocuments.find(
          typeDocument => typeDocument.id == 1
        ).id,
        currency_id: quotationClone.currency_id,
        client_id: quotationClone.client_id,
        date_issue: this.toDate,
        type_invoice_id: 1,
        total_discount: 0,
        id: quotation.id,
        total_tax: 0,
        watch: false,
        subtotal: 0,
        items: items,
        total: 0,
        sale: 0
      };

      this.document = JSON.parse(JSON.stringify(quotationClone));
      this.editMode = true;
      this.dialog = true;
    },
    validateEdit(scope) {
      this.$validator.validateAll(scope).then(valid => {
        if (valid) {
          this.loading = true;

          axios
            .put(`${this.route}/${this.document.id}`, this.document)
            .then(response => {
              this.$setLaravelMessage(response.data);

              if (response.data.success) {
                this.refreshData();

                this.dialog = false;
              }
            })
            .catch(error => {
              this.$setLaravelValidationErrorsFromResponse(error.response.data);
              this.$setLaravelErrors(error.response.data);
            })
            .then(() => {
              this.loading = false;
            });
        }
      });
    },
    toBill(quotation) {
      //console.log(quotation)
     
     
      this.loadDataTable = true;
      quotation.toBill = true;

       let invoice = this.createInvoideService(quotation)

      axios
        .post(`${this.route}/${quotation.id}/to-bill`, { service_invoice : invoice })
        .then(response => {
            
          this.$setLaravelMessage(response.data);

          if (response.data.success) {
            this.refreshData();

            this.dialog = false;
          }
        })
        .catch(error => {
          this.$setLaravelValidationErrorsFromResponse(error.response.data);
          this.$setLaravelErrors(error.response.data);
        })
        .then(() => {
          this.loadDataTable = false;
          quotation.toBill = false;
        });
    },
    downloadPDF(quotation) {
      window.location = `${this.route}/download/pdf/${quotation.id}`;
    },
    getTaxTotal(detail) {
      let tax = [];
      detail.forEach(element => {
        let find = tax.find(
          x =>
            x.tax_id == element.tax.type_tax_id && x.percent == element.tax.rate
        );
        if (find) {
          let indexobj = tax.findIndex(
            x =>
              x.tax_id == element.tax.type_tax_id &&
              x.percent == element.tax.rate
          );
          tax.splice(indexobj, 1);
          tax.push({
            tax_id: find.tax_id,
            tax_amount: Number(find.tax_amount) + Number(element.total_tax),
            percent: find.percent,
            taxable_amount:
              Number(find.taxable_amount) +
              Number(element.price) * Number(element.quantity)
          });
        } else {
          tax.push({
            tax_id: element.tax.type_tax_id,
            tax_amount: Number(element.total_tax),
            percent: Number(element.tax.rate),
            taxable_amount: Number(element.price) * Number(element.quantity)
          });
        }
      });

      this.tax_amount_calculate = tax;
      return tax;
    },
    getLegacyMonetaryTotal(detail) {
      let line_ext_am = 0;
      //let tax_excl_am = 0
      let tax_incl_am = 0;
      detail.forEach(element => {
        line_ext_am += Number(element.price) * Number(element.quantity);
      });

      let total_tax_amount = 0;
      this.tax_amount_calculate.forEach(element => {
        total_tax_amount += Number(element.tax_amount);
      });
      tax_incl_am = line_ext_am + total_tax_amount;
      return {
        line_extension_amount: line_ext_am,
        tax_exclusive_amount: line_ext_am,
        tax_inclusive_amount: tax_incl_am,
        allowance_total_amount: "0.00",
        charge_total_amount: "0.00",
        payable_amount: this.document.total
      };
    },
    getInvoiceLines(detail) {
      let data = detail.map(x => {
        return {
          unit_measure_id: 94, //codigo api dian de unidad
          invoiced_quantity: x.quantity,
          line_extension_amount: Number(x.price) * Number(x.quantity),
          free_of_charge_indicator: false,
          tax_totals: [
            {
              tax_id: x.tax.type_tax_id,
              tax_amount: x.total_tax,
              taxable_amount: Number(x.price) * Number(x.quantity),
              percent: x.tax.rate
            }
          ],
          description: x.item.name,
          code: x.item.code,
          type_item_identification_id: 4,
          price_amount: x.price,
          base_quantity: x.quantity
        };
      });

      return data;
    },
    createInvoideService(dato) {
      
      let resol = this.resolution.resolution;
      const invoice = {
        number: 0,
        type_document_id: 1
      };
      invoice.customer = {
        identification_number: dato.client.identification_number,
        name: dato.client.name,
        phone: dato.client.phone,
        address: dato.client.address,
        email: dato.client.email,
        merchant_registration: "000000"
      };
      invoice.tax_totals = this.getTaxTotal(dato.detail_quotations);
      invoice.legal_monetary_totals = this.getLegacyMonetaryTotal(dato.detail_quotations);
      invoice.invoice_lines = this.getInvoiceLines(dato.detail_quotations);

      return invoice;
    }
  }
};
</script>
