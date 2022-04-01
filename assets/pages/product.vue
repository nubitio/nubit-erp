<template>
  <div>
    <h2>Products</h2>
    <DataGrid ref="dg" :data="data" :filterable="true" style="min-width:700px;height:250px">
      <GridColumn field="itemid" title="Item ID"></GridColumn>
      <GridColumn field="name" title="Name"></GridColumn>
      <GridColumn field="listprice" title="List Price" align="right" :filterOperators="operators">
        <template slot="filter" slot-scope="scope">
          <NumberBox v-model="scope.column.filterValue" :precision="1" class="f-full">
          </NumberBox>
        </template>
      </GridColumn>
      <GridColumn field="unitcost" title="Unit Cost" align="right" :filterOperators="operators">
        <template slot="filter" slot-scope="scope">
          <NumberBox v-model="scope.column.filterValue" class="f-full">
          </NumberBox>
        </template>
      </GridColumn>
      <GridColumn field="attr" title="Attribute" width="30%"></GridColumn>
      <GridColumn field="status" title="Status" align="center">
        <template slot="filter">
          <ComboBox class="f-full"
                    :data="status"
                    :editable="false"
                    :inputStyle="{textAlign:'center'}"
                    @valueChange="$refs.dg.doFilter({field:'status',op:'equal',value:$event.currentValue})">
          </ComboBox>
        </template>
      </GridColumn>
    </DataGrid>
  </div>
</template>

<script>
export default {
  name: "product",
  data() {
    return {
      data: [],
      operators: ["nofilter", "equal", "notequal", "less", "greater"],
      status: [
        {value: null, text: "All"},
        {value: "P", text: "P"},
        {value: "N", text: "N"}
      ]
    }
  },
  mounted() {
    fetch('producto/get')
        .then(response => response.json())
        .then(json => {
          this.data = json;
        })
        .catch(error => console.error(error))
  }
}
</script>

<style scoped>

</style>