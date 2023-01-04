<template>
  <div class="container">
    <DxForm
        ref="dxform"
        v-bind:form-data="editables"
        :on-content-ready="validateForm"
        class="gap"
    >
      <DxItem
          v-for="(column, index) in columns"
          :key="`col${index}`"
          :data-field="column.dataField"
          :data-type="column.dataType"
          :is-required="column.isRequired"
          :visible="column.visible"
          :disabled="!column.allowEditing"
          :label="{ text: column.caption }"
          :editor-type="column.editorType"
          :validation-rules="column.validation"
          :editor-options="column.editorOptions"
          :items="column.items"
      >
      </DxItem>
    </DxForm>
    <DxButton
        :width="120"
        name="Submit"
        text="Submit"
        type="normal"
        styling-mode="primary"
        @click="onSubmitClick($event)"
    />
  </div>
</template>

<script lang="ts">
import {DxForm, DxItem} from "devextreme-vue/form";
import "devextreme-vue/text-area";
import DxButton from "devextreme-vue/button";
import ProductDto from "./product.dto";
import {defineComponent, PropType} from "vue";
import axios from "axios";
import dayjs from "dayjs";

export default defineComponent({
  name: "FormView",
  components: {
    DxButton,
    DxForm,
    DxItem,
  },
  props: {
    controller: String,
    columns: Array,
    update_url: String,
    insert_url: String,
    editables: {
      type: Object as PropType<ProductDto>,
      default: () => ({
        id: 0,
        name: "",
        description: "",
        price: 0,
        quantity: 0,
        category: "",
        image: "",
        created_at: dayjs().format("YYYY-MM-DD HH:mm:ss"),
        updated_at: dayjs().format("YYYY-MM-DD HH:mm:ss"),
      }),
    },
  },
  methods: {
    onSubmitClick() {
      const form = this.$refs.dxform as any;
      if (form.instance.validate().isValid) {
        if (this.editables?.id !== "") {
          this.sendCrudRequest(
              this.update_url ?? "",
              "post",
              this.editables?.id,
              this.editables
          );
        } else {
          this.sendCrudRequest(this.insert_url ?? "", "post", null, this.editables);
        }
      }
    },
    validateForm(e: any) {
      e.component.validate();
    },
    sendCrudRequest(_url: string, _method: any, _key: any, _values: any) {
      let _this = this;
      const options = {
        method: _method,
        url: _url,
        headers: {
          "Content-Type": "application/json",
        },
        data: [{data: _values, pk: "id", id: _key}],
      };
      return axios
          .request(options)
          .then(function (response) {
            _this.$emit("onSubmitted", response);
          })
          .catch(function (error) {
            console.log(error);
          });
    },
  },
});
</script>

<style>
.gap {
  margin-bottom: 15px;
}
</style>