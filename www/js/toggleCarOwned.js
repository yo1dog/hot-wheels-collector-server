function toggleCarOwned(elem)
{
	if (elem.getAttribute("data-pending") === "1")
		return false;
	
	elem.setAttribute("data-pending", "1");
	elem.className += " pending";
	
	var carID = elem.getAttribute("data-car-id");
	var owned = elem.getAttribute("data-owned") === "1";
	
	owned = !owned;
	
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.open("POST", "/api/setCarOwned.php", true);
	
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState === 4)
		{
			elem.setAttribute("data-pending", "0");
			elem.className = elem.className.substring(0, elem.className.length - 8);
			
			if (xmlhttp.status === 200)
			{
				elem.children[0].setAttribute("src", owned? "/img/owned.png" : "/img/unowned.png");
				elem.setAttribute("data-owned", owned? "1" : "0")
			}
		}
	};
	
	xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlhttp.send("carID=" + encodeURIComponent(carID) + "&owned=" + (owned? "1" : "0"));
	return false;
}