<template>
  <div>
    <h2>Basic Dialog</h2>
    <LinkButton @click="$refs.dialog.open()">Open Dialog</LinkButton>
    <Dialog ref="dialog"
            :title="'Product'"
            :dialogStyle="{width: '400px', height: '200px'}"
            :modal="true"
            :draggable="true"
            :resizable="false">
      <Form class="fm" ref="form" :model="product" labelPosition="top" id="producto_fm" method="post" novalidate>
        <FormField name="descripcion" label="Producto">
          <TextBox></TextBox>
        </FormField>
        <FormField name="categoria_id" label="Categoria">
          <ComboBox></ComboBox>
        </FormField>
        <FormField name="precio" label="Precio">
          <TextBox></TextBox>
        </FormField>
        <FormField name="estado" label="Activo">
          <SwitchButton class="rounded"></SwitchButton>
        </FormField>
      </Form>
    </Dialog>
  </div>
</template>

<script>
export default {
  name: "product",
  data() {
    return {
      product: {
        descripcion: '',
        categoria_id: '',
        precio: '',
        estado: ''
      }
    }
  },
}
</script>

<style scoped>

</style>