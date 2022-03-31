<template>
  <Dialog ref="dialog"
          :title="'Product'"
          :dialogStyle="{width: '400px', height: '200px'}"
          :modal="true"
          :draggable="true"
          :resizable="false">
    <p>Dialog content</p>
  </Dialog>
</template>

<script>
export default {
  name: "product"
}
</script>

<style scoped>

</style>