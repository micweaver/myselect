// myselect
//该程序正在开发中，不可用，仅作备份用
package main

import (
	"fmt"
	"os"
	"regexp"
	"strconv"
	"strings"
)

const ITEM_MAX_LEN = 1000

type type_sqlfun struct {
	name   string
	params []string
}

var sql string
var tokens []string
var key_word_status map[string]int = map[string]int{
	"select": 1,
	"from":   2,
	"where":  3,
	"group":  4,
	"having": 5,
	"order":  6,
	"limit":  7,
}

var key_word_vals map[string]string = make(map[string]string)
var key_work_parse_vals map[string]interface{} = make(map[string]interface{})

var fun_fun_pattern = "^([a-zA-Z]{1,8})\\((\\S+)\\)$"
var fun_params_separater = "@"

var str_fun map[string]bool = map[string]bool{
	"regsub": true,
	"strsub": true,
}

var group_fun map[string]bool = map[string]bool{
	"count": true,
	"sum":   true,
	"avg":   true,
	"max":   true,
	"min":   true,
}

var field_fun map[string]bool = map[string]bool{
	"regsub": true,
	"strsub": true,
	"count":  true,
	"sum":    true,
	"avg":    true,
	"max":    true,
	"min":    true,
}

var relation_operator []string = []string{"=", "!=", ">", "<", ">=", "<=", "like", "rlike"}
var special_relation_operator []string = []string{"=", "!=", ">=", "<=", ">", "<"}

var new_fun_field map[string]type_sqlfun = make(map[string]type_sqlfun)
var select_group_fun type_sqlfun = type_sqlfun{}

var group_all = false
var view_syntax_parse = false
var view_compute_process = false

var item [ITEM_MAX_LEN]byte

var fp = fmt.Println

func parseError(errmsg string) {
	fmt.Println(errmsg + "\n")
	os.Exit(0)
}

func setViewSynTaxParse(val bool) {
	view_syntax_parse = val
}

func setViewComputeProcess(val bool) {
	view_compute_process = val
}

func printParseVal(val interface{}, comment string) {

	if view_syntax_parse {
		if comment != "" {
			fmt.Println("**" + comment + "**")
		}
		myPrint(val)
	}

}

func printComputeVal(val interface{}, comment string) {

	if view_compute_process {
		if comment != "" {
			fmt.Println("**" + comment + "**")
		}
		myPrint(val)
	}

}

func myPrint(data interface{}) {

	if v, ok := data.(map[string]string); ok {
		for key, val := range v {
			fmt.Println(key, ":", val)
		}
		return
	}

	if v, ok := data.(map[string]int); ok {
		for key, val := range v {
			fmt.Println(key, ":", val)
		}
		return
	}

	if v, ok := data.(map[string]bool); ok {
		for key, val := range v {
			fmt.Println(key, ":", val)
		}
		return
	}

	if v, ok := data.(map[string]type_sqlfun); ok {
		for key, val := range v {
			fmt.Println(key, ":", val)
		}
		return
	}

	if v, ok := data.([]string); ok {
		for key, val := range v {
			fmt.Println(key, ":", val)
		}
		return
	}

	if v, ok := data.([]int); ok {
		for key, val := range v {
			fmt.Println(key, ":", val)
		}
		return
	}

	fmt.Println(data)
}

func substr(str string, start, slen int) string {

	olen := len(str)
	var subres []byte
	for i := 0; i < olen; i++ {
		if i >= start {
			if slen <= 0 {
				subres = append(subres, str[i])
			} else if i < start+slen {
				subres = append(subres, str[i])
			} else {
				break
			}
		}
	}
	return string(subres)
}

func isSpace(char byte) bool {
	if char == '\n' || char == '\r' || char == '\t' || char == '\v' || char == '\f' || char == ' ' {
		return true
	}
	return false
}

func splitLine(line string) []string {
	var res []string
	linelen := len(line)
	status := 0
	item_index := 0

	for i := 0; i < linelen; i++ {
		if (line[i] == '\'' || line[i] == '"') && (i == 0 || line[i-1] != '\\') {
			if status == 3 {
				new_item := item[:item_index]
				res = append(res, string(new_item))
				status = 0
				item_index = 0
			} else {
				if status == 1 {
					new_item := item[:item_index]
					res = append(res, string(new_item))
					item_index = 0
				}
				status = 3
			}
		} else if isSpace(line[i]) {
			if status == 3 {
				item[item_index] = line[i]
				item_index++
			} else if status == 2 || status == 0 {
				status = 2
			} else {
				new_item := item[:item_index]
				res = append(res, string(new_item))
				item_index = 0
				status = 2
			}
		} else {
			if status == 3 {
				item[item_index] = line[i]
				item_index++
			} else {
				item[item_index] = line[i]
				item_index++
				status = 1
			}
		}
	}

	if (item_index) > 0 {
		new_item := item[:item_index]
		res = append(res, string(new_item))

	}

	return res
}

func getFunSignature(fun type_sqlfun) string {

	params := strings.Join(fun.params, fun_params_separater)
	return fun.name + "_" + params

}
func minit(input_sql string) {
	sql = strings.Trim(input_sql, "")
}

func sqlRegSub() {
	fp("ss==", sql)
	pattern := "(?iU:regsub\\(\\s*([^,]+),\\s*(\\/\\S+\\/[imsxeADSUXJu]*)\\s*,\\s*([^,]+)\\))"
	re := regexp.MustCompile(pattern)
	matches := re.FindStringSubmatch(sql)

	if len(matches) <= 0 {
		return
	}

	myPrint(matches)
	matches[2] = strings.Replace(matches[2], "(", "<<<", -1)
	matches[2] = strings.Replace(matches[2], ")", ">>>", -1)
	replacement := "regsub(" + strings.Trim(matches[1], " ") + "," + strings.Trim(matches[2], " ") + "," + strings.Trim(matches[3], " ") + ")"
	fp("re==", replacement)
	sql = re.ReplaceAllLiteralString(sql, replacement)
	fp("end==", sql)
}

func sqlStrSub() {

	pattern := "(?iU:strsub\\s*\\(\\s*([\\$0-9]+)\\s*,\\s*([0-9]+)\\s*,\\s*([0-9]+)\\s*\\))"
	fp(pattern)
	re := regexp.MustCompile(pattern)
	sql = re.ReplaceAllString(sql, "strsub($1,$2,$3)")

	pattern = "(?iU:strsub\\s*\\(\\s*([\\$0-9]+)\\s*,\\s*([0-9]+)\\s*\\))"
	re = regexp.MustCompile(pattern)
	sql = re.ReplaceAllString(sql, "strsub($1,$2)")
	fp(sql)
}

func replace_fun_param_sep(match string) string {
	return strings.Replace(match, ",", string(fun_params_separater), -1)
}

func getTokenArray() {

	pattern := "([a-zA-Z]{1,8})\\s*\\(\\s*(\\S+)\\s*\\)" //清除空格
	re := regexp.MustCompile(pattern)
	//matchs := re.FindAllString(sql, -1)
	//fmt.Println(matchs)
	sql = re.ReplaceAllString(sql, "$1($2)")
	sqlRegSub()
	sqlStrSub()
	pattern = "[a-zA-Z]{1,8}\\([^\\(\\)]+\\)" //将函数参数分隔符进行替换
	re = regexp.MustCompile(pattern)
	sql = re.ReplaceAllStringFunc(sql, replace_fun_param_sep)
	fp(sql)
	tokens = strings.Split(sql, " ")
	fp("tokens:")
	myPrint(tokens)
}

func isFuncField(str string) (bool, type_sqlfun) {
	re := regexp.MustCompile(fun_fun_pattern)
	matches := re.FindStringSubmatch(str)
	var fun_res type_sqlfun
	if len(matches) > 0 {
		fun_res.name = matches[1]
		fun_res.params = strings.Split(matches[2], fun_params_separater)
		return true, fun_res
	}
	return false, fun_res
}

func checkFunParam(fun type_sqlfun) {
	switch fun.name {
	case "regsub":
		if len(fun.params) != 3 {
			parseError(fun.name + " function params error")
		}
		re := regexp.MustCompile("^\\$\\d{1,3}")
		if !re.MatchString(fun.params[0]) {
			parseError(fun.name + " function params error")
		}
	case "strsub":
		if len(fun.params) > 3 || len(fun.params) < 2 {
			parseError(fun.name + " function params num error")
		}
		m, _ := regexp.MatchString("^\\$\\d{1,3}", fun.params[0])
		fp("^\\$\\d{1,3}")
		if !m {
			parseError(fun.name + " function params error")
		}

	case "count,sum,avg,max,min":
		if len(fun.params) != 1 {
			parseError(fun.name + " function params error")
		}

		m, _ := regexp.MatchString("^\\$\\d{1,3}", fun.params[0])
		if !m {
			parseError(fun.name + " function params error")
		}
	}
}

func getField(str string, validFun map[string]bool) interface{} {

	if str[0] == '$' {
		field := substr(str, 1, 0)
		fieldint, err := strconv.Atoi(field)
		if err != nil {
			parseError("field" + str + " wrong")
		}
		if fieldint < 1 {
			parseError("field num should greater than 1")
		}
		return fieldint
	} else if res, fun_res := isFuncField(str); res {
		fp("funres:", fun_res)
		//myPrint(validFun)
		if _, ok := validFun[fun_res.name]; !ok {
			parseError("unsupport function" + fun_res.name)
		}

		if _, ok := field_fun[fun_res.name]; ok {
			new_fun_field[getFunSignature(fun_res)] = fun_res
		}
		//myPrint(new_fun_field)
		checkFunParam(fun_res)
		return fun_res

	} else {
		parseError("field" + str + " format  wrong")
	}

	return nil
}

func getExpression(str string) []string {

	items := splitLine(strings.Trim(str, " "))
	if (len(items) == 3) && (items[1] == "like" || items[1] == "rlike") {
		return items
	}

	op := strings.Join(special_relation_operator, "|")
	pattern := "(\\S+)\\s+(" + op + ")\\s+(\\S+)"
	re := regexp.MustCompile(pattern)
	matches := re.FindStringSubmatch(str)
	if len(matches) == 4 {
		return []string{matches[1], matches[2], matches[3]}
	}

	parseError("expression " + str + " wrong")

	return nil

}

func parseSql() {
	getTokenArray()

	cur_status := 0
	next_status := 0
	cur_val := ""
	cur_key := ""

	for _, val := range tokens {
		lval := strings.ToLower(val)
		switch lval {

		case "select":
			next_status, _ = key_word_status[lval]
			cur_key = lval
			cur_val = ""
		case "from", "where", "group", "order", "limit":
			next_status, _ = key_word_status[lval]
			key_word_vals[cur_key] = strings.Trim(cur_val, " ")
			cur_key = lval
			cur_val = ""
		case "having":
			group_status, _ := key_word_status["group"]
			if cur_status != group_status {
				parseError(" having  should after group")
			}
			next_status, _ = key_word_status[lval]
			key_word_vals[cur_key] = strings.Trim(cur_val, " ")
			cur_key = lval
			cur_val = ""
		default:
			cur_val = cur_val + " " + val
		}

		if cur_status == 0 && len(cur_val) > 1 {
			parseError("sql syntax error,some thing before select")
		}

		if next_status < cur_status {
			parseError("sql syntax error,wrong sequence")
		}
		cur_status = next_status
	}

	key_word_vals[cur_key] = strings.Trim(cur_val, " ")

	_, ok := key_word_vals["select"]
	_, ok1 := key_word_vals["from"]

	if !ok || !ok1 {
		parseError("sql syntax error,need select and from")
	}

	fp("key word parse:")
	myPrint(key_word_vals)

	for key, val := range key_word_vals {
		switch key {
		case "select":
			key_work_parse_vals[key] = parseSelect(val)
		case "from":
			key_work_parse_vals[key] = parseFrom(val)
		case "where":
			key_work_parse_vals[key] = parseWhere(val)
		case "group":
			key_work_parse_vals[key] = parseGroup(val)
		case "having":
			key_work_parse_vals[key] = parseHaving(val)
		case "order":
			key_work_parse_vals[key] = parseOrder(val)
		case "limit":
			key_work_parse_vals[key] = parseLimit(val)

		}
	}

	myPrint(key_work_parse_vals)

}

func parseFrom(str string) string {
	printParseVal(str, "parseFrom")
	if str == "" {
		parseError("sql syntax error after from")
	}
	str = strings.Trim(str, " ")
	if _, err := os.Open(str); err != nil {
		parseError("file " + str + " not exist")
	}
	return str

}

func parseWhere(str string) [][]interface{} {
	printParseVal(str, "parseWhere")
	if str == "" {
		parseError("sql syntax error after where")
	}
	expression := strings.Split(str, "and")
	var out [][]interface{}
	var res []string
	var field interface{}
	var items []interface{}

	for _, val := range expression {
		res = getExpression(val)
		field = getField(res[0], str_fun)
		items = []interface{}{field, res[1], res[2]}
		out = append(out, items)
	}
	return out
}

func cmpTypeSqlFun(fun1, fun2 type_sqlfun) bool {

	if fun1.name != fun2.name {
		return false
	}

	for key, val := range fun1.params {
		if val != fun2.params[key] {
			return false
		}
	}

	return true
}

func is_field_in_array(val interface{}, arr interface{}) bool {

	newarr, ok := arr.([]interface{})

	if !ok {
		return false
	}

	if vv, ok := val.(type_sqlfun); ok {
		for _, va := range newarr {
			if vta, ok := va.(type_sqlfun); ok {
				if cmpTypeSqlFun(vv, vta) {
					return true
				}
			}
		}

		return false
	}

	if vv, ok := val.(int); ok {
		for _, va := range newarr {
			if vta, ok := va.(int); ok {
				if vv == vta {
					return true
				}
			}
		}

		return false
	}

	return false
}

func parseGroup(str string) []interface{} {

	printParseVal(str, "parseGroup")
	if str == "" {
		parseError("sql syntax error after group")
	}

	str = strings.Trim(str, " ")

	if str[0] != 'b' || str[1] != 'y' {
		parseError("should group by")
	}

	str = substr(str, 2, 0)
	str = strings.Trim(str, " ")
	field := strings.Split(str, ",")

	var out []interface{}
	for _, val := range field {
		out = append(out, getField(val, str_fun))
	}

	var s_f []interface{}
	select_fields := key_work_parse_vals["select"]
	s_f = select_fields.([]interface{})

	for _, val := range s_f {
		if v, ok := val.(type_sqlfun); ok {
			if _, ok := str_fun[v.name]; ok {
				if is_field_in_array(val, out) {
					continue
				}
				parseError("select field " + getFunSignature(v) + " not in group fields")
			}
		} else {
			if is_field_in_array(val, out) {
				continue
			}
			parseError("select field " + val.(string) + " not in group fields")
		}

	}

	if select_group_fun.name == "" {
		parseError("select should have group function")
	}

	group_all = false

	return out

}

func parseHaving(str string) []interface{} {
	printParseVal(str, "parseHaving")
	if str == "" {
		parseError("sql syntax error after having")
	}

	expression := getExpression(str)
	g_fun := getField(expression[0], group_fun)

	v, ok := g_fun.(type_sqlfun)
	if !ok {
		parseError("having field wrong")
	}

	if !cmpTypeSqlFun(v, select_group_fun) {
		parseError("the field is not the same as the select field")
	}

	return []interface{}{g_fun, expression[1], expression[2]}
}

func parseOrder(str string) map[string]interface{} {

	printParseVal(str, "parseOrder")
	if str == "" {
		parseError("sql syntax error after order")
	}

	str = strings.Trim(str, " ")

	if str[0] != 'b' || str[1] != 'y' {
		parseError("should order by")
	}

	var out map[string]interface{}
	var out_fields []interface{}

	out["sort"] = "asc"

	pattern := "(asc|desc)$"
	re := regexp.MustCompile(pattern)
	matches := re.FindStringSubmatch(str)
	if len(matches) > 0 {
		out["sort"] = matches[1]
		str = re.ReplaceAllString(str, "")
	}

	str = substr(str, 2, 0)
	str = strings.Trim(str, " ")
	field := strings.Split(str, ",")

	for _, val := range field {
		out_fields = append(out_fields, getField(val, field_fun))
	}

	if _, ok := key_work_parse_vals["group"]; ok {
		for _, vo := range out_fields {
			if !is_field_in_array(vo, key_work_parse_vals["select"]) {
				parseError("the order by field should in the select field")
			}
		}
	}

	out["fields"] = out_fields

	return out

}

func parseLimit(str string) map[string]int {
	printParseVal(str, "parseLimit")
	if str == "" {
		parseError("sql syntax error after limit")
	}

	var out map[string]int
	var err interface{}

	str = strings.Trim(str, " ")
	items := strings.Split(str, ",")
	if len(items) == 1 {
		out["start"] = 0
		out["count"], err = strconv.Atoi(items[0])
		if err != nil {
			parseError("limit " + items[0] + " wrong")
		}
	} else if len(items) == 2 {
		out["start"], err = strconv.Atoi(items[0])
		if err != nil {
			parseError("limit " + items[0] + " wrong")
		}
		out["count"], err = strconv.Atoi(items[1])
		if err != nil {
			parseError("limit " + items[1] + " wrong")
		}
	} else {
		parseError("sql syntax error after limit")
	}

	return out
}

func parseSelect(str string) interface{} {

	printParseVal(str, "parseSelect")
	if str == "" {
		parseError("sql syntax error after select")
	}

	items := strings.Split(str, ",")
	var out []interface{}

	for _, val := range items {
		val = strings.Trim(val, " ")
		if val == "*" {
			if len(out) > 0 {
				parseError("field error,include * and other fields")
			}
			out = append(out, val)
			break
		} else {
			out = append(out, getField(val, field_fun))
		}
	}

	myPrint(out)
	for _, val := range out {
		if v, ok := val.(type_sqlfun); ok {
			if _, ok := group_fun[v.name]; ok {
				if select_group_fun.name != "" {
					parseError("should be only one group function")
				}
				select_group_fun = v
				group_all = true
				printParseVal(select_group_fun, "select group fun")
			}
		}
	}
	return out
}

func main() {
	/*fmt.Println("Hello World!")

	logline := "aaa \"b bb\" ccc dd"

	logitems := splitLine(logline)

	fmt.Println(logitems)

	for _, val := range logitems {
		fmt.Printf("%s\n", val)
	}
	*/
	//fmt.Println(fun_fun_pattern)

	//res := getField("$1", field_fun)
	//res := getField("strsub($3,3,6)", field_fun)

	//res := getExpression(" aa = bb ")
	//myPrint(res)
	//return
	sql = "select $1,count ($2 ) FROM c.txt   group by $1  order by count ($2 ) desc LIMIT 5"
	//sql = "select strsub( $3, 3,6 ),regsub($2,/(.+):(.+):(.+)/i,\\2),count($1) from b.txt"

	//parseSelect("")
	//return
	minit(sql)
	fmt.Printf("%s\n", sql)

	setViewSynTaxParse(true)
	//getTokenArray()
	//fmt.Printf("%s\n", strings.Trim(sql, ""))

	parseSql()

	return

	for _, val := range tokens {
		fmt.Printf("%s\n", val)
	}

	for key, val := range key_word_status {
		fmt.Printf("%s %d\n", key, val)
	}
	//fmt.Println(sql)
}
