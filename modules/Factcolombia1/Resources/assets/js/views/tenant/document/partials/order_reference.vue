<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @open="create" width="30%" :close-on-click-modal="false" :close-on-press-escape="false" :show-close="false" append-to-body>
 
 
        <div class="row mt-3">
            <div class="col-lg-6 col-md-6">
                <div class="form-group">
                    <label class="control-label">Fecha de orden</label>
                    <el-date-picker v-model="order_reference.issue_date_order" type="date" value-format="yyyy-MM-dd" :clearable="false" ></el-date-picker>
                </div>
            </div>
            <div class="col-lg-6 col-md-6">
                <div class="form-group">
                    <label class="control-label">Referencia de orden</label>
                    <el-input v-model="order_reference.id_order"></el-input>
                </div>
            </div>
        </div> 
        <span slot="footer" class="dialog-footer">
            <el-button @click="clickClose">Cancelar</el-button>
            <el-button type="primary" @click="clickSave">Guardar</el-button>
        </span>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'order_reference'],
        data() {
            return {
                titleDialog: 'Orden de pago',
                loading: false,
                resource: 'co-documents',
                errors: {},
                form: {},
                company: {},
                locked_emission:{}
            }
        },
        async created() {
        },
        methods: { 
            async create() {

            },
            validate(){

                if(!this.order_reference.issue_date_order) return {success:false, message:'El campo fecha de orden es obligatorio'}
                if(!this.order_reference.id_order) return {success:false, message:'El campo referencia de orden es obligatorio'}

                return {success:true}

            },
            clickSave() {
                
                let validate = this.validate()
                if(!validate.success) return this.$message.error(validate.message)

                this.close()
            },
            cleanData(){
                this.$emit('addOrderReference', {});
            },
            clickClose() {
                this.cleanData()
                this.close()
            },
            close(){
                this.$emit('update:showDialog', false)
            },
        }
    }
</script>
